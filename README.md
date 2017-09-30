# Drupal 7 site for WellnessPolygon.com.ua

## Quickstart
1. Make sure that `composer`, `VirtualBox`, `Vagrant` and plugins are installed.
2. Clone project and navigate into dir.
3. `composer build`

## Available commands
- `build` - build project
- `rebuild` - cleanup code dependencies and rebuild site, skipping VM management.
- `rebuild-full` - cleanup code dependencies, remove VM and rebuild site
- `build-db` - re-import DB and run updates
- `build-theme` - build theme assets. Supports watching: `composer build-theme -- watch`
- `cleanup` - remove code dependencies, skipping VM management
- `cleanup-full` - remove code dependencies and remove VM

## Adding Drupal modules
`composer require drupal/module_name`

## Applying patches
1. Add patch information to `composer.json`
2. `composer rebuild`

One caveat is that a removal of `composer.lock` may be required before step #2
as composer caches dependencies and patches may not be applied.
