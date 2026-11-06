# README

[![Release](https://img.shields.io/github/v/release/jcdenis/rateIt?color=lightblue)](https://github.com/JcDenis/rateIt/releases)
![Date](https://img.shields.io/github/release-date/jcdenis/rateIt?color=red)
[![Dotclear](https://img.shields.io/badge/dotclear-v2.39-137bbb.svg)](https://fr.dotclear.org/download)
[![License](https://img.shields.io/github/license/jcdenis/rateIt?color=white)](https://github.com/JcDenis/rateIt/blob/master/LICENSE)

## ABOUT

_rateIt_ is a plugin for the open-source web publishing software called [Dotclear](https://www.dotclear.org).

> Rating system for your blogs: entries, comments, categories,
tags, events (plugin eventHandler) and links (plugin cinecturlink2).

## REQUIREMENTS

* Dotclear 2.39
* PHP 8.1+
* Dotclear admin permissions to configure plugin
* Dotclear contentadmin permissions to manage votes

## USAGE

First install _rateIt_, manualy from a zip package or from
Dotclear plugins repository. (See Dotclear's documentation to know how do this)

Enable plugin and select types of items that can be rated
from plugin configuration page. Three rating styles are available:

* classic: visitor gives a note to an item (stars),
* twin: visitor says if he likes or not an item,
* simple: visitor just says if he likes an item.

Rating is displayed after entries content or after comments
according to options, with widget _Rate it: rating_ or with template tags.
Widget _Rate it: top rating_ lists top rated items.

The default template _rateit.html_ can be copied to your theme to customize it.
Template tags are the same as old versions:

* `<tpl:rateIt type="post" style="classic">` : rating block of current item
* `<tpl:rateItIf has_vote="1" user_voted="0" type="post" style="!twin">` : conditions
* `{{tpl:rateItTitle}}`, `{{tpl:rateItLinker}}`, `{{tpl:rateItAnchor}}`
* `{{tpl:rateItNote}}`, `{{tpl:rateItFullnote}}`, `{{tpl:rateItQuotient}}`, `{{tpl:rateItMax}}`, `{{tpl:rateItMin}}`
* `{{tpl:rateItTotal}}`, `{{tpl:rateItMaxCount}}`, `{{tpl:rateItMinCount}}`

Votes are managed from menu _Rate it_ under "Plugins" group.

Voters are identified by IP address, cookie or both.
IP addresses are not stored, only a hash of them.

Other plugins can add types of rated items with behavior `rateItModules`
(add an instance of class `Dotclear\Plugin\rateIt\Module` to the given ArrayObject).

## LINKS

* [License](https://github.com/JcDenis/rateIt/blob/master/LICENSE)
* [Packages & details](https://github.com/JcDenis/rateIt/releases)
* [Sources & contributions](https://github.com/JcDenis/rateIt)
* [Issues & security](https://github.com/JcDenis/rateIt/issues)

## CONTRIBUTORS

* Jean-Christian Denis (author)
* Nicolas Roudaire (2014-2016)
* Pierre Van Glabeke
* Claude (AI assistant by Anthropic)

You are welcome to contribute to this code.
