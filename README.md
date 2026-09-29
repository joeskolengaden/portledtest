# Port & LED Tester (FPP plugin)

A pure UI plugin for [Falcon Player (FPP)](https://github.com/FalconChristmas/fpp) that
lights up **one physical output port at a time** and steps through it **one pixel at a
time**, so you can visually count how many LEDs on a strand actually light up. Built for
verifying a new run of pixels, or finding exactly where a strand died, without pulling
out a multimeter.

No `fppd` changes, no compiled component - this plugin only reads the ports you've
already configured under **Content Setup → Channel Outputs** and drives FPP's own,
long-standing `/api/testmode` endpoint (the same one the stock **Status/Control → Testing**
page uses). That endpoint has existed since well before FPP 5.4, so this works on any FPP
from **5.4 onward**.

## What it does

- **Reads your real port configuration** (`config/channeloutputs.json`) - `BBB48String`,
  `BBBSerial`, `RPIWS281X`-style outputs with `virtualStrings`, and generic channel-range
  outputs - and lists every configured port with its channel range, pixel count, color
  order, null-node offset and group count.
- **Whole-port solid fill** - light an entire port white/red/green/blue (or a custom raw
  wire-byte color) to confirm it's wired and alive.
- **Count mode** - steps a single pixel (or a block of pixels, with adjustable step size)
  down the port, with Prev/Next, jump-to-#, and auto-play at a chosen speed, so you can
  watch where the light actually travels and stop the instant it goes dark.
- **"This is where it stopped"** - one click captures the current step as the observed
  count; **Save result** records configured-vs-observed per port with a timestamp, so
  mismatches (dead sections, wrong pixel count in config) are visible at a glance in the
  port table.
- **Fill all ports at once** - a quick whole-rig sanity check before diving into individual
  ports.
- **All Off / Stop Test** - always available, and fired automatically when you leave the
  page.

## Install

Content Setup → Plugins → paste this URL → Install → restart `fppd`:

```
https://github.com/joeskolengaden/portledtest/blob/main/pluginInfo.json
```

Then open **Content Setup → Port & LED Tester**.

## How it works

FPP's channel tester (`ChannelTester` / `TestPatternBase` in `fppd`) already exposes a
`GET`/`POST /api/testmode` endpoint that overlays a solid fill (`RGBFill`) or other test
patterns onto an arbitrary, even multi-range (`;`-joined), channel set - completely
independent of any plugin. This plugin's `action.php` just:

1. Parses `channeloutputs.json` into a flat list of testable channel ranges ("ports").
2. Calls `POST /api/testmode` with `mode: RGBFill` and a `channelSet` scoped to either the
   whole port or a single pixel's channels, depending on what you clicked.
3. Saves your observed-count entries to `config/plugin.portledtest.results.json`.

Because it rides on the stock test-mode overlay, it behaves exactly like the built-in
Testing page - one test pattern is active system-wide at a time, and it takes over output
regardless of play/idle state, so stop playback first.

## Notes

- Colors in the "Fill whole port" panel are **raw wire-byte order** (channel 1/2/3 as sent
  to the string), not logical RGB - they work the same regardless of a port's configured
  `colorOrder`, matching how the stock Testing page's color pickers behave.
- Grouped strings (`groupCount > 1`) step by *addressable* channel position; the panel
  shows which physical LED range that maps to.
- Disabled channel-output blocks are still listed (so you can find them) but flagged
  "disabled" - enable them under Channel Outputs first if you need real output.

## License

GPL-2.0-or-later, matching FPP itself.
