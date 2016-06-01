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
      "key": "FILL-IT", // The kraken API key
      "secret": "FILL-IT" // The kraken API secret
    }
  ],
  "timer": 3, // The refresh timer
  "engaged": 450, // The engaged amount
  "lightName": "My beside lamp", // The lamp name
  "colorBound": 2, // If the rate falls from 2 euros or more, it goes to the straight red, if it rises from 2 euros or more, it goes to the straight green. Between, it shade.
  "lightsEnabled": true,
  "phueIp": "FILL-IT", // The phillips hue bridge IP
  "phueKey": "FILL-IT", // The phillips hue username
  "KrakenCurrency": "ZEUR" // The kraken currency code (see Kraken API for more)
}
```
