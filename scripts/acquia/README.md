Acquia scripts
==============

Cron runs overnight
-------------------
```
STEP_COPY_DB_LEGACY=1 refresh-environment-acquia.sh
  |-source refresh-environment-acquia.variables.$ENV.sh
  |-copy-files-acquia.sh
  |-copy-db-acquia.sh - for main
  |-copy-db-acquia.sh - for migration
  |-hooks/post-code-update/*
```    
    
When code is deployed (branch switched)
---------------------------------------
```    
hooks/post-code-deploy/1.refresh-environment.sh    
  |-scripts/refresh-environment-acquia.sh
```
  
When code is updated (new commits pushed to the branch)
-------------------------------------------------------
```
hooks/post-code-update/1.drush-cache-clear.sh
  |-drush cc all
hooks/post-code-update/2.db-update.sh
  |-drush updb
  |-drush en vp_core
hooks/post-code-update/3.enable-shield.sh
  |-drush en shield
  |-drush vset shield_name && drush vset shield_pass  
hooks/post-code-update/4.flush-varnish.sh
  |-scripts/purge-cache-acquia.sh
    |-source refresh-environment-acquia.variables.$ENV.sh 
```
     
Environment refresh
-------------------
```    
refresh-environment-acquia.sh
  |-source refresh-environment-acquia.variables.$ENV.sh
  |-copy-files-acquia.sh
  |-copy-db-acquia.sh - for main
  |-copy-db-acquia.sh - for migration
  |-hooks/post-code-update/*    
```
