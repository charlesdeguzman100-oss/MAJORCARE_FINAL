# Brand assets

The layout detects these files automatically — no code changes are needed.

## Owl mark only (the image you supplied)

Save the owl image as:

```
assets/img/majorcare-logo.png
```

Because this crop has no wordmark, the sidebar and login page will place the text
**MajorCare** next to / below the owl for you.

## Full lockup instead

If you have the complete logo with the MAJORCARE wordmark built in, save it as:

```
assets/img/majorcare-logo-full.png
```

When that file exists it is used on its own and the text wordmark is hidden, so
nothing is duplicated.

## Favicon

```
assets/img/favicon.png
```

Referenced from `templates/header.php` as both `rel="icon"` and `rel="apple-touch-icon"`,
so it appears on every page (browser tabs and mobile home screens). The PNG is served
directly — no `.ico` conversion is needed.

## Notes

- Keep the original artwork unchanged: owl mascot, graduation cap, stethoscope.
- A transparent or white background looks best; the light-blue box version also works.
- If neither file is present, a clean "MC" text fallback is shown.
