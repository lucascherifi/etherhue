EtherHue
========

Change a Philips Hue lamp color according to a kraken rate.

I.e. :

- Your bedside lamp starts at a yellow color when you start this script.
- If the rate falls, the color goes to shades of red.
- If the rate rises, the color goes to shades of green.

## Options

```json
{
  "krakenKeys": [
    {
      "key": "FILL-IT",
      "secret": "FILL-IT"
    }
  ],
  "timer": 3,
  "engaged": 450,
  "lightName": "My beside lamp",
  "colorBound": 2,
  "lightsEnabled": false,
  "phueIp": "FILL-IT",
  "phueKey": "FILL-IT",
  "KrakenCurrency": "ZEUR"
}
```
