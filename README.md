# Moderation
[![Build Status](https://scrutinizer-ci.com/g/WildPHP/module-moderation/badges/build.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-moderation/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/WildPHP/module-moderation/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-moderation/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/wildphp/module-moderation/v/stable)](https://packagist.org/packages/wildphp/module-moderation)
[![Latest Unstable Version](https://poser.pugx.org/wildphp/module-moderation/v/unstable)](https://packagist.org/packages/wildphp/module-moderation)
[![Total Downloads](https://poser.pugx.org/wildphp/module-moderation/downloads)](https://packagist.org/packages/wildphp/module-moderation)

Moderation commands for WildPHP.

## System Requirements
If your setup can run the main bot, it can run this module as well.

## Installation
To install this module, we will use `composer`:

```composer require wildphp/module-moderation```

That will install all required files for the module. In order to activate the module, add the following line to your modules array in `config.neon`:

    - WildPHP\Modules\Moderation\Moderation

The bot will run the module the next time it is started.

## Usage
This module provides the following commands:

* **ban**:
    * Bans the specified user from the channel.
    * Usage #1: `ban [nickname] [minutes]`
    * Usage #2: `ban [nickname] [minutes] [redirect channel]`
    * Pass 0 minutes for an indefinite ban.
    * Permission: `ban`
* **banhost**:
    * Bans the specified host from the channel.
    * Usage #1: `banhost [hostname] [minutes]`
    * Usage #2: `banhost [hostname] [minutes] [redirect channel]`
    * Pass 0 minutes for an indefinite ban.
    * Permission: `ban`
* **kban**:
    * Kicks the specified user from the channel and adds a ban.
    * Usage #1: `kban [nickname] [minutes] ([reason])`
    * Usage #2: `kban [nickname] [minutes] [redirect channel] ([reason])`
    * Pass 0 minutes for an indefinite ban.
    * Permission: `kban`
* **kick**:
    * Kicks the specified user from the channel.
    * Usage: `kick [nickname] ([reason])`
    * Permission: `kick`
* **mode**:
    * Changes mode for a specified entity.
    * Usage: `mode [mode(s)] ([entity/ies])`
    * Permission: `mode`
* **remove**:
    * Requests a user to leave the channel.
    * Usage: `remove [nickname] ([reason])`
    * Permission: `remove`
* **topic**:
    * Changes the topic for the specified channel.
    * Usage: `topic ([channel]) [message]`
    * Permission: `topic`
    

## License
This module is licensed under the MIT license. Please see `LICENSE` to read it.
