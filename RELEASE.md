# Releases

The following are steps for releasing a new version of club.

## Git steps
```
git tag VERSION_NUMBER
git push origin master
```

# Building a phar file
```
./vendor/bin/box build
```



## Troubleshooting.
### Build Fails
Symfony has an issue when building phars sometimes and errors with the following:
```
PHP Fatal error:  Uncaught exception 'ErrorException' with message 'proc_open(): unable to create pipe Too many open files' in phar:///usr/local/bin/box.phar/src/vendors/symfony/console/Application.php:954
```

Workaround:
```
ulimit -Sn 4096
```
### ./vendor/bin/box not found
Make sure you are on latest github master code and run
`composer install`
