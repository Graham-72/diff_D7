DESCRIPTION
----------------
This module adds a tab for sufficiently permissioned users. The tab shows all revisions like standard Drupal
but it also allows pretty viewing of all added/changed/deleted words between revisions.

INSTALL
----------------
Install as usual for Drupal modules. If you are using the HTML tag filter, you should
allow the <ins> and <del> tags since those are used by this module.

TECHNICAL
-------------------
The PEAR Diff library comes with this module, and powers the comparing of revisions.

TODO
-----------------
Fix an 'off by one' bug when viewing differences
Handle custom node types better. currently only looks for changes in $node->body. Any ideas?
When we compare $node->body changes, we should do so 'post filtering', I think.
