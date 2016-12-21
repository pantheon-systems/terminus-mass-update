<?php

namespace Pantheon\TerminusMassUpdate\Commands;

// @TODO: Autoloading.
require_once "MassUpdateCommandBase.php";

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class ListCommand extends MassUpdateCommandBase
{
    protected $command = 'sites:mass-update:list';

    /**
     * List all available upstream updates from all sites.
     *
     * @authorize
     *
     * @command site:mass-update:list
     *
     * @field-labels
     *   site: Site Name
     *   datetime: Timestamp
     *   message: Message
     *   author: Author
     * @param array $options
     * @return RowsOfFields
     * 
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @option upstream Update only sites using the given upstream
     */
    public function listAllUpdates($options = ['upstream' => '', 'format' => 'table'])
    {
        $updates = $this->flattenUpdates($this->getAllSitesAndUpdates($options));
        return new RowsOfFields($updates);
    }

    /**
     * Flatten the updates into a single list for output.
     *
     * @param $updates
     * @return array
     */
    protected function flattenUpdates($updates) {
        $out = [];
        foreach ($updates as $site) {
            $out += $site['updates'];
        }
        return $out;
    }
}
