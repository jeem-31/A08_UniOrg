# Website palette

Updated 6 November 2026 for the brochure-led research website redesign.

The authoritative brand colors were supplied by the researchers and checked against the two-page UNIORG A4 brochure. They override the former blue/gold application-derived website theme.

| Role | Color | CSS token |
| --- | --- | --- |
| University navy | #0F2A5C | --navy |
| Maroon accent | #7A1414 | --maroon |
| White | #FFFFFF | --white |
| Primary text | #17233b | --ink |
| Supporting text | #526078 | --muted |
| Subtle surface | #f6f7f9 | --paper |
| Neutral tint | #edf0f5 | --tint |
| Divider | #d5dbe5 | --line |

The homepage, documentation hub, diagram collection and illustrated manual use these shared presentation tokens. Inter provides the responsive typography throughout. The supplied LSPU seal replaces the generic U in navigation and the favicon; the original PNG remains unchanged. CSS removes only the square corner background. The favicon is a small browser rendering of that exact seal.

Organization workspaces are configurable and may have their own colors. Source screenshots, masks and diagrams preserve their original colors and details as research evidence. The Laravel application under source-code was not changed.

The previous palette was #26488C / #1A3461 / #C4973B / #F9FBFF, based on the auth/OSAS source theme. That historical application palette remains in the separate Laravel snapshot and is no longer the research website theme.

See [design decisions and audit](REDESIGN.md), [browser QA](REDESIGN_QA.md), and `design-system/uniorg/MASTER.md` for the generated design-system workflow and explicit brand overrides.
