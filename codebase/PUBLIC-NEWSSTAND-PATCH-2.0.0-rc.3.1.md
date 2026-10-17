# EP-DI v2.0.0-rc.3.1 — Exact Public Newsstand Patch Structure

The current EP-DI renderer is PHP SSR (`app/App.php`) rather than Blade. RC.3 already contains the required semantic markup, so rc.3.1 intentionally changes CSS/delivery cache-busters only; no template/backend rewrite is needed.

## Homepage hero
Selector: `.newsstand-hero-copy::before`
- soft radial editorial tint
- 32 px blur
- isolated behind copy

Selector: `.newsstand-hero-cover img`
- 3 px white border
- `object-fit: contain`
- stable newspaper aspect ratio
- 190 ms hover/focus transform
- `translateY(-4px) rotate(3deg) scale(1.01)`

## Recent editions
Selector: `.edition-rail`
- native horizontal overflow
- `scroll-snap-type: x mandatory`
- `touch-action: pan-x`
- desktop cards 190–220 px
- mobile cards 160 px to 48vw

Selector: `.edition-tile-cover img`
- `object-fit: contain`
- no image border/shadow distortion; frame owns border/shadow

## Archive
Selector: `.archive-grid`
- desktop: 3 columns
- <=1100 px: 2 columns
- <=700 px: 1 column

Selector: `.archive-cover img`
- `object-fit: contain`
- stable newspaper aspect ratio

## Accessibility
- focus-visible outline on Today cover
- reduced-motion disables swing/lift transforms
- existing alt text and intrinsic thumbnail dimensions remain unchanged
