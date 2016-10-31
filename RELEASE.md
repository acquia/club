# Releases

The following are steps for releasing a new version of club.

## Git steps
```
git tag VERSION_NUMBER
git push origin master
```

## Building a phar file
```
./vendor/bin/box build
```

## Github steps
1. Create a new [release](https://github.com/acquia/club/releases/new])
2. Add Release notes with [changelog generator](https://github.com/skywinder/github-changelog-generator)
3. Upload club.phar
4. Click Publish release



## Troubleshooting.
### Box phar build fails
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
