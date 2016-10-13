[![Build Status](https://travis-ci.com/acquia/club.svg?token=eFBAT6vQ9cqDh1Sed5Mw&branch=master)](https://travis-ci.com/acquia/club)

# Club: Command Line Utility for BLT

`club` is a command line utility for managing BLT and accessing Acquia cloud resources.


-------
<p align="center">
    <a href="#features">Features</a> &bull;
    <a href="#installation">Installation</a> &bull;
    <a href="#usage">Usage</a> &bull;
    <a href="#contributing-to-club">Contributing</a>
</p>

-------

## Features

| :metal: | club
--------------------------|------------------------------------------------------------
:sparkles: | Generate aliases for ACSF and AC that are attached to your account.
:rocket: | Pull existing projects from Acquia Cloud and automatically load them into a VM.
:wrench: | Create new projects for <a href="http://github.com/acquia/blt">BLT</a>
:cake: | build recipes to deploy consistent projects throughout your team.

## Installation
Currently `club` supports 3 installation methods. Preferred installation method is homebrew as it will ensure all dependencies are install on your system.

### Homebrew
```
brew tap homebrew/php
brew install club
```

### Manual phar install

```
wget http://linktocustom.phar
mv club.phar /usr/local/bin
```

### Manual git checkout

```
git clone https://github.com/acquia/club.git
cd club
composer install
box build
mv club.phar /usr/local/bin
```

## Usage
For the following commands to work, you will need access to your Acquia Cloud API keys.

- `club ac-aliases` will generate all of your Acquia Cloud site subscription aliases. If you have Acquia Cloud SIte Factory, it will also generate all of your aliases for all of your sites on your factory.
- `club create-project` will ask various questions, ensure that certain settings are made, build a new BLT project, build a local VM with your new site and push to Acquia cloud if you want.
- `club pull-project` will pull an existing Acquia Cloud cloud to your local, build it in a vm, and sync files and dabatases if needed.

## Contributing to Club

    git clone https://github.com/acquia/club.git
    composer install --working-dir=club
    ./club/bin/club list
