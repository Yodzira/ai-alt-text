=== AI Alt Text ===
Contributors: yodzira
Tags: alt text, accessibility, media library, ai, images
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Media library full of images without alt text? Generate drafts in batches with your own AI vision key — review and approve. Nothing goes live without you.

== Description ==

Missing alt text is the most common accessibility defect. Filling it by hand for hundreds of images takes days. AI Alt Text does the boring part:

* scans the media library for attachments without alt
* generates draft alt texts in batches (your own OpenAI-compatible key — bring your own key)
* you review: Apply or Reject, one click each
* style control: plain description or SEO-friendly; language of your choice
* daily token cap and a "spent today" counter — no surprise bills
* approved texts go to the native alt field; any other plugin sees them

Nothing is applied without your approval. The key stays in your database.

== Installation ==

1. Install and activate.
2. Open AI Alt Text → paste your API key → Save.
3. "Generate next batch" → review drafts → Apply.

== Frequently Asked Questions ==

= Does it apply alt texts automatically? =
No. Drafts wait for your approval. (Nothing is published without you.)

= Where does the image go? =
To your chosen AI provider, using your key. No intermediate services.

== Changelog ==

= 0.1.0 =
* First release: media scan, batch generation with BYO-key, approval queue, daily token cap, clean uninstall (key wiped).
