<?php
/**
 * HostResources.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS\Traits;

use LibreNMS\Device\Processor;

trait HostResources
{
    /**
     * Discover processors.
     * Returns an array of LibreNMS\Device\Processor objects that have been discovered
     *
     * @return array Processors
     */
    public function discoverProcessors()
    {
        echo "Host Resources: ";
        $processors = [];

        try {
            $hrProcessorLoad = $this->getCacheByIndex('hrProcessorLoad', 'HOST-RESOURCES-MIB');

            if (empty($hrProcessorLoad)) {
                // no hr data, return
                return [];
            }

            $hrDeviceDescr = $this->getCacheByIndex('hrDeviceDescr', 'HOST-RESOURCES-MIB');
        } catch (\Exception $e) {
            return [];
        }

        foreach ($hrProcessorLoad as $index => $usage) {
            $usage_oid = '.1.3.6.1.2.1.25.3.3.1.2.' . $index;
            $descr = $hrDeviceDescr[$index];

            if (! is_numeric($usage)) {
                continue;
            }

            $device = $this->getDeviceArray();
            if ($device['os'] == 'arista-eos' && $index == '1') {
                continue;
            }

            if (empty($descr)
                || $descr == 'Unknown Processor Type' // Windows: Unknown Processor Type
                || $descr == 'An electronic chip that makes the computer work.'
            ) {
                $descr = 'Processor';
            } else {
                // Make the description a bit shorter
                $remove_strings = [
                    'GenuineIntel: ',
                    'AuthenticAMD: ',
                    'CPU ',
                    '(TM)',
                    '(R)',
                ];
                $descr = str_replace($remove_strings, '', $descr);
                $descr = str_replace('  ', ' ', $descr);
            }

            $old_name = ['hrProcessor', $index];
            $new_name = ['processor', 'hr', $index];
            rrd_file_rename($this->getDeviceArray(), $old_name, $new_name);

            $processor = Processor::discover(
                'hr',
                $this->getDeviceId(),
                $usage_oid,
                $index,
                $descr,
                1,
                $usage,
                null,
                null,
                $index
            );

            if ($processor->isValid()) {
                $processors[] = $processor;
            }
        }

        return $processors;
    }
}
