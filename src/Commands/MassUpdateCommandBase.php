<?php

namespace Pantheon\TerminusMassUpdate\Commands;

use Pantheon\Terminus\Commands\Upstream\Updates\UpdatesCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;

class MassUpdateCommandBase extends UpdatesCommand
{
    // This allows us to provide contextual help for usage.
    protected $command = '';

    /**
     * Get a list of the sites and updates with the given options.
     *
     * @return array
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    protected function getAllSitesAndUpdates($options) {
        $sites = $this->getAllSites($options);
        $this->log()->notice("Found {count} sites.", ['count' => count($sites)]);
        $this->log()->notice("Fetching the list of available updates for each site...");
        $updates = $this->getAllUpdates($sites);
        $this->log()->notice(
            "{sites} sites need updates.",
            ['sites' => count($updates)]);

        return $updates;
    }
    /**
     * Get a list of all of the sites the user has access to or the sites passed in via STDIN for chaining.
     * 
     * @param $options
     * @return array
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    protected function getAllSites($options) {
        $sites = $this->readSitesFromSTDIN();
        if (empty($sites)) {
            throw new TerminusException(
                'Input a list of sites by piping it to this command. Try running "terminus sites:list | terminus {cmd}".',
                ['cmd' => $this->command]
            );
        }

        // Filter by upstream
        if (!empty($options['upstream'])) {
            foreach ($sites as $id => $site) {
                $upstream = $site->getUpstream();
                if ($upstream->id != $options['upstream']) {
                    unset($sites[$id]);
                }
            }
            if (empty($sites)) {
                throw new TerminusException('None of the specified sites use the given upstream.');
            }
        }

        return $sites;
    }

    /**
     * Get the list of updates for all of the sites passed in.
     *
     * @param $sites
     * @return array | An array containing a list of sites which need updates (index 0) and the updates themselves (1)
     */
    protected function getAllUpdates($sites) {
        $out = [];
        foreach ($sites as $site) {
            foreach ($this->getUpstreamUpdatesLog($site) as $commit) {
                $out[$site->id]['updates'][] = [
                    'site' => $site->getName(),
                    'datetime' => $commit->datetime,
                    'message' => trim($commit->message),
                    'author' => $commit->author,
                ];
                $out[$site->id]['site'] = $site;
            }
        }
        return $out;
    }

    /**
     * Read a list of site ids passed through STDIN and load the sites.
     *
     * @return array
     */
    protected function readSitesFromStdin() {
        // If STDIN is interactive then nothing was piped to the command. We don't want to hang forever waiting
        // for input as this is not meant to be interactive.
        if (posix_isatty(STDIN)) {
            return [];
        }
        $sites = [];
        while ($line = trim(fgets(STDIN)))
        {
            try {
                $sites[] = $this->sites->get($line);
            }
            catch (TerminusException $e) {
                // If the line isn't a valid site id, ignore it.
                continue;
            }
        }
        return $sites;
    }
}
