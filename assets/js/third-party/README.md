# Self-hosted animation dependencies

GSAP 3.14.2 and ScrollTrigger 3.14.2 were obtained from the published `gsap` package through jsDelivr. Original copyright and license notices are retained in both minified files. Terms: https://gsap.com/standard-license/.

The research website does not require an npm install, CDN, build or backend. If animation scripts are unavailable, its HTML/CSS content stays readable. These libraries are used only on the homepage; shared navigation, carousel and image viewers use vanilla JavaScript.

Formatted document previews use Marked 18.1.3 (MIT license) and DOMPurify 3.4.17 (license files included). Both were obtained from their official published npm packages and are loaded locally only when a document preview is first requested. Markdown is parsed and sanitized before rendering; relative links resolve against the source document. No source files under source-code are modified.
