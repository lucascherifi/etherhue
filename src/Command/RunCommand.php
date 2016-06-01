<?php
namespace Kasifi\Etherhue\Command;

use Exception;
use Kasifi\Etherhue\KrakenAPIClient;
use Kasifi\Etherhue\LightManager;
use Phue\Light;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    /** @var double */
    private $eb;

    /** @var double */
    private $lastEb;

    /** @var double */
    private $displayedLastEb;

    /** @var double */
    private $stableEb;

    /** @var double */
    private $initEb;

    /** @var int */
    private $timerCounter;

    /** @var int */
    private $globalCounter;

    /** @var double */
    private $colorBound;

    /** @var int */
    private $percent;

    /** @var LightManager */
    private $lightManager;

    /** @var string */
    private $lightName;

    /** @var array */
    private $krakenKeys;

    /** @var OutputInterface */
    private $output;

    /** @var KrakenAPIClient */
    private $krakenApi;

    /** @var int Seconds. */
    private $timer;

    /** @var int */
    private $engaged;

    /** @var boolean */
    private $lightsEnabled;

    /** @var Light */
    private $light;

    /** @var string */
    private $phueIp;

    /** @var string */
    private $phueKey;

    /** @var string */
    private $krakenCurrency;

    /**
     * return void
     */
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Start Etherhue watcher');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->initConfig();
        if ($this->lightsEnabled) {
            $this->lightManager = new LightManager($this->phueIp, $this->phueKey);
        }

        $alertGap = 0.01; // in euros

        $this->initKrakenApi();

        $this->stableEb = null;
        $this->lastEb = null;
        $this->displayedLastEb = null;
        $this->eb = null;
        $this->timerCounter = $this->timer;
        $this->globalCounter = 0;

        if ($this->lightsEnabled) {
            $this->light = $this->lightManager->getLightByName($this->lightName);
        }

        try {
            if ($this->lightsEnabled) {
                $this->lightManager->storeInitialState($this->light);
            }

            $progress = new ProgressBar($output, $this->timer);
            $progress->setFormat('%message% %memory:6s%');
            while (42) {
                $progress->setMessage($this->formatMessage());
                $progress->advance();

                if ($this->timerCounter == $this->timer) {
                    $res = $this->krakenApi->QueryPrivate('TradeBalance', ['asset' => $this->krakenCurrency]);
                    if (!isset($res['result']) || !isset($res['result']['eb'])) {
                        if ($res['error'] && $res['error'] = 'EAPI:Rate limit exceeded') {
                            $this->output->writeln('Rate limit exceeded. Changing API key..');
                            $this->initKrakenApi();
                        } else {
                            throw new Exception(json_encode($res));
                        }
                    }
                    $eb = floatval($res['result']['eb']);
                    if ($this->eb == null) {
                        $this->initEb = $eb;
                    }
                    $this->eb = $eb;
                    $progress->setMessage($this->formatMessage());
                    $progress->advance();

                    if ($this->stableEb == null) {
                        $this->stableEb = $this->eb;
                    }

                    if ($this->lastEb == null) {
                        $this->lastEb = $this->eb;
                        $this->displayedLastEb = $this->eb;
                    }

                    if ($this->eb > $this->lastEb && $this->eb > ($this->stableEb + $alertGap)) {
                        $this->stableEb = $this->eb;
                    } elseif ($this->eb < ($this->stableEb - $alertGap)) {
                        $this->stableEb = $this->eb;
                    }

                    $this->lightGap();
                    $progress->setMessage($this->formatMessage());
                    $progress->advance();
                    $this->displayedLastEb = $this->lastEb;
                    $this->lastEb = $this->eb;
                }
                sleep(1);
                $this->timerCounter--;
                $this->globalCounter++;
                if ($this->timerCounter == 0) {
                    $this->timerCounter = $this->timer;
                }
            }
        } catch (Exception $e) {
            if ($this->lightsEnabled) {
                $this->light->setOn(false);
            }
            throw $e;
        }
    }

    /**
     *
     */
    private function initConfig()
    {
        $config = json_decode(file_get_contents('config.json'), true);

        $this->krakenKeys = $config['krakenKeys'];
        $this->timer = $config['timer'];
        $this->engaged = $config['engaged'];
        $this->lightName = $config['lightName']; // 'canapé'
        $this->colorBound = $config['colorBound'];
        $this->lightsEnabled = $config['lightsEnabled'];
        $this->phueIp = $config['phueIp'];
        $this->phueKey = $config['phueKey'];
        $this->krakenCurrency = $config['krakenCurrency'];
    }

    /**
     *
     */
    private function initKrakenApi()
    {
        $key = array_rand($this->krakenKeys);
        $this->output->writeln("Selecting Kraken API key #{$key}.");
        $krakenKey = $this->krakenKeys[$key];
        $this->krakenApi = new KrakenAPIClient($krakenKey['key'], $krakenKey['secret']);
    }

    /**
     * @return string
     */
    private function formatMessage()
    {
        $gap = round($this->eb - $this->displayedLastEb, 4);
        $startGap = round($this->eb - $this->initEb, 4);
        $startGap = $startGap > 0 ? '+' . $startGap : $startGap;
        $startGap = str_pad($startGap, 10, ' ', STR_PAD_LEFT);
        $percent = round($this->percent * 100);
        $gain = $this->eb - $this->engaged;

        return "[{$startGap}€] | Start: {$this->initEb}€ | Now: {$this->eb}€ | Gain: {$gain}€ | Last alert: {$this->stableEb}€ | Instant gap: {$gap}€ | Light color: {$percent}% | {$this->globalCounter}s (wait {$this->timerCounter}s)";
    }

    /**
     *
     */
    private function lightGap()
    {
        $gap = $this->eb - $this->initEb;
        if ($gap > $this->colorBound) {
            $gap = $this->colorBound;
        }
        if ($gap < -$this->colorBound) {
            $gap = -$this->colorBound;
        }
        $this->percent = (($this->colorBound + $gap) / ($this->colorBound * 2));
        if ($this->lightsEnabled) {
            $this->lightManager->setIndicator($this->lightName, $this->percent);
        }
    }
}
