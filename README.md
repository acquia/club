[![Build Status](https://travis-ci.com/acquia/club.svg?token=eFBAT6vQ9cqDh1Sed5Mw&branch=master)](https://travis-ci.com/acquia/club)

**Club does not currently have a stable release. It should be considered experimental.**

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
brew install acquia/tools/club
```

### Manual phar install

```
curl -OL http://github.com/acquia/club/releases/download/0.1/club.phar
chmod u+x club.phar
mv club.phar /usr/local/bin/club
```

### Manual git checkout

```
git clone https://github.com/acquia/club.git
cd club
composer install
./vendor/bin/box build
chmod u+x club.phar
mv club.phar /usr/local/bin/club
```

## Usage
For the following commands to work, you will need access to your Acquia Cloud API keys.

- `club ac-aliases` will generate all of your Acquia Cloud site subscription aliases. If you have Acquia Cloud SIte Factory, it will also generate all of your aliases for all of your sites on your factory.
- `club create-project` will ask various questions, ensure that certain settings are made, build a new BLT project, build a local VM with your new site and push to Acquia cloud if you want.
- `club pull-project` will pull an existing Acquia Cloud cloud to your local, build it in a vm, and sync files and dabatases if needed.

## FAQ

#### What is the difference between Club and BLT?

Club is a standalone executable tool that is used to create or clone existing BLT-based projects. Exactly one version of Club is installed per machine. It is not intended to be a dependency of a Drupal application, and it should never be committed to a codebase.

BLT is a project-specific tool (not standalone). It is intended to be a dependency of a Drupal application. You may have multiple projects, each using a different version of BLT, on one machine.

| Characteristic                         | BLT | Club |
|----------------------------------------|-----|------|
| Standalone, installed to local machine |     |  x   |
| Works outside of project context       |     |  x   |
| Multple versions per machine           |  x  |      |
| Committed to project codebase          |  x  |      |
| Managed via Composer                   |  x  |      |
| Managed via Homebrew                   |     |  x   | 

#### Why aren't Club and BLT the same tool?

It's not feasible to combine Club and BLT at this time. Club creates projects. BLT is one part of the created project.

You can execute a Club command outside the context of a Drupal application directory. Conversely, BLT requires a Drupal application directory.

Some tools (e.g., Drush) have worked around similar issues by allowing you to have a "global" version of Drush on your machine in addition to a project-specific version. The global version defers to the project-specific version by way of a separate "launcher" layer.

This is helpful, but often leads to confusion. It's possible, but unlikely, that we'll refactor BLT to behave in this way at some point in the future.

## Contributing to Club

Please see [CONTRIBUTING.md](CONTRIBUTING.md)
