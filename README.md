# Terminus Mass Update Plugin
[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/pantheon-systems/terminus-plugin-example/tree/1.x)


A Terminus plugin that applies upstream updates to a list of sites.

This plugin contains two commands:

### terminus site:mass-update:apply

Apply the available upstream updates for each of the sites specified.

To specify the list of sites to apply updates to you must send them to this function on stdin using a pipe. This allows you to use any other Terminus command to generate the list of sites to work on:

```
$ terminus site:list --format=list | terminus sites:mass-update:apply
$ terminus org:site:list --format=list | terminus sites:mass-update:apply
```

By adding `--format=list` to a Terminus command you will get a list of site IDs suitable for input into this command.

When working with organizations it can be useful to filter `org:site:list` by tags as well. One strategy is to create a `mass-update` tag in order to apply mass updates to specific sites within an organization using `terminus org:site:list --tag="mass-update" --format=list | terminus sites:mass-update:apply`.

The mass-update command has some other options:

- `--dry-run`: Show what updates would be applied but do not apply them.
- `--updatedb`: Run update.php after updating (Drupal only)
- `--accept-upstream`: Attempt to automatically resolve conflicts in favor of the upstream
- `--upstream=<upstream id>`: Update only sites using the given upstream

### terminus site:mass-update:list

List the available upstream updates for each of the sites specified.

Input for this function works the same way as the `apply` command. This command can be used to discover exactly which update would be applied by `apply`

```
$ terminus site:list --format=list | terminus sites:mass-update:list
$ terminus org:site:list --format=list | terminus sites:mass-update:list
```

## Installation
To install this plugin place it in `~/.terminus/plugins/`.

On Mac OS/Linux:
```
mkdir -p ~/.terminus/plugins
curl https://github.com/pantheon-systems/terminus-mass-update/archive/1.x.tar.gz -L | tar -C ~/.terminus/plugins -xvz
```

## Help
Run `terminus help sites:mass-update:list` or `terminus help sites:mass-update:apply` for help.

