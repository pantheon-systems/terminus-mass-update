<?php

namespace Pantheon\TerminusMassUpdate\Commands;

// @TODO: Autoloading.
use Pantheon\Terminus\Exceptions\TerminusException;

require_once "MassUpdateCommandBase.php";

class ApplyCommand extends MassUpdateCommandBase
{
    protected $command = 'site:mass-update:apply';

    /**
     * Apply all available upstream updates to all sites.
     *
     * @authorize
     *
     * @command site:mass-update:apply
     * @aliases mass-update
     *
     * @param array $options
     * @return RowsOfFields
     * 
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @option upstream Update only sites using the given upstream
     * @option boolean $updatedb Run update.php after updating (Drupal only)
     * @option boolean $accept-upstream Attempt to automatically resolve conflicts in favor of the upstream
     * @option dry-run Don't actually apply the updates
     * @option force-git-mode Force Git mode on the environment
     * @option continue-on-failure Don't exit if a site update fails
     */
    public function applyAllUpdates($options = ['upstream' => '', 'updatedb' => false, 'accept-upstream' => false, 'dry-run' => false, 'force-git-mode' => false, 'continue-on-failure' => false])
    {
        $site_updates = $this->getAllSitesAndUpdates($options);
        foreach ($site_updates as $info) {
            $site = $info['site'];
            $updates = $info['updates'];

            $env = $site->getEnvironments()->get('dev');

            if ($env->get('connection_mode') !== 'git') {
                if ($options['force-git-mode'])
                {
                    $workflow = $env->changeConnectionMode('git');
                    if(is_string($workflow))
                    {
                        $this->log()->notice($workflow);
                    }
                    else
                    {
                        while(!$workflow->checkProgress())
                        {
                        }
                        $this->log()->notice($workflow->getMessage());
                    }
                    $this->applyUpdates($options, $site, $updates, $env);
                }
                else {
                    $this->log()->warning(
                        'Cannot apply updates to {site} because the dev environment is not in git mode.',
                        ['site' => $site->getName()]
                    );
                }
            }
            else {
                $this->applyUpdates($options, $site, $updates, $env);
            }
        }
    }

    /**
     * @param $options
     * @param $site
     * @param $updates
     * @param $env
     */
    protected function applyUpdates($options, $site, $updates, $env)
    {
        $logname = $options['dry-run'] ? 'DRY RUN' : 'notice';
        $this->log()->notice(
            'Applying {updates} updates to {site}',
            ['site' => $site->getName(), 'updates' => count($updates), 'name' => $logname]);

        // Do the actual updates if we're not in dry-run mode
        if (!$options['dry-run']) {
            // @TODO: We may be able to run workflows asynchronously to save time.
            try {
                $workflow = $env->applyUpstreamUpdates(
                    isset($options['updatedb']) ? $options['updatedb'] : false,
                    isset($options['accept-upstream']) ? $options['accept-upstream'] : false
                );
                while (!$workflow->checkProgress()) {
                    // @TODO: Add Symfony progress bar to indicate that something is happening.
                }
                $this->log()->notice($workflow->getMessage());
            }
            catch(\Exception $e) {
                if (!$options['continue-on-failure']) {
                    throw new TerminusException($e->getMessage());
                }
                else {
                    $this->log()->warning($e->getMessage());
                }
            }
        }
    }
}
