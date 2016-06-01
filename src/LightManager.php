<?php

namespace Kasifi\Etherhue;

use Phue\Client;
use Phue\Light;

class LightManager
{
    /** @var Client */
    private $client;

    /** @var array */
    private $initialState;

    /** @var array */
    private $modifiedState;

    public function __construct($host, $username)
    {
        $this->client = new Client($host, $username);
        $this->lights = $this->client->getLights();

        // From the client
        foreach ($this->lights as $lightId => $light) {
            echo "#{$lightId} - {$light->getName()}", "\n";
        }
    }

    public function success($name)
    {
        $this->alert($name, 50, 0.2, 0.6);
    }

    public function danger($name)
    {
        $this->alert($name, 50, 0.62, 0.34);
    }

    public function setIndicator($name, $percent)
    {
        $light = $this->getLightByName($name);
        if (!$light->isOn()) {
            $light->setOn(true);
            $light->setBrightness(0);
        }
        $light->setSaturation(255);
        $light->setHue(25500*$percent);
    }

    /**
     * @param $name
     *
     * @return null|Light
     */
    public function getLightByName($name)
    {
        foreach ($this->lights as $lightId => $light) {
            if ($name == $light->getName()) {
                return $light;
            }
        }

        return null;
    }

    public function storeInitialState(Light $light)
    {
        $xy = $light->getXY();
        $this->initialState = [
            $light->getName() => [
                'on'         => $light->isOn(),
                'brightness' => $light->getBrightness(),
                'saturation' => $light->getSaturation(),
                'x'          => $xy['x'],
                'y'          => $xy['y'],
            ],
        ];
    }

    /**
     * @param Light  $light
     * @param int    $brightnessVariation 0 => 255
     * @param double $x                   @see http://www.developers.meethue.com/documentation/core-concepts
     * @param double $y                   @see http://www.developers.meethue.com/documentation/core-concepts
     */
    private function configureModifiedState(Light $light, $brightnessVariation, $x, $y)
    {
        $initialState = $this->initialState[$light->getName()];
        if ($initialState['on']) {
            $brightness = min(255, $initialState['brightness'] + $brightnessVariation);
        } else {
            $brightness = 20;
        }
        $this->modifiedState = [
            $light->getName() => [
                'brightness' => $brightness,
                'x'          => $x,
                'y'          => $y,
            ],
        ];
    }

    /**
     * @param Light $light
     */
    private function setModifiedState(Light $light, $flash = true)
    {
        $modifiedState = $this->modifiedState[$light->getName()];
        $light->setOn(true);
        $light->setBrightness($modifiedState['brightness']);
        $light->setXY($modifiedState['x'], $modifiedState['y']);

        if ($flash) {
            usleep(500000);
            $light->setBrightness(max(0, $modifiedState['brightness'] - 100));
            usleep(500000);
            $light->setBrightness($modifiedState['brightness']);
        }
    }

    /**
     * @param Light $light
     */
    public function revertInitialState(Light $light)
    {
        $initialState = $this->initialState[$light->getName()];
        $light->setOn($initialState['on']);
        if ($initialState['on']) {
            $light->setBrightness($initialState['brightness']);
            $light->setSaturation($initialState['saturation']);
            $light->setXY($initialState['x'], $initialState['y']);
        }
    }

    /**
     * @param string $name
     * @param int    $brightnessVariation
     * @param double $x
     * @param double $y
     */
    private function alert($name, $brightnessVariation, $x, $y)
    {
        $light = $this->getLightByName($name);
        $this->storeInitialState($light);
        $this->configureModifiedState($light, $brightnessVariation, $x, $y);

        $light->setOn(false);
        usleep(500000);

        $this->setModifiedState($light);
        usleep(2000000);
        $this->revertInitialState($light);
    }
}
