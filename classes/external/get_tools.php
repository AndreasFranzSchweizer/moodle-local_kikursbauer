<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_kikursbauer\external;

use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_kikursbauer\helper;

/**
 * Describes the functions of the service as tools with JSON schema, so the local bridge needs no own function list.
 *
 * @package    local_kikursbauer
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_tools extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the tool list.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kikursbauer:use', $context);

        $service = $DB->get_record('external_services', ['shortname' => helper::SERVICE_SHORTNAME], '*', MUST_EXIST);
        $names = $DB->get_fieldset_select('external_services_functions', 'functionname', 'externalserviceid = ?',
            [$service->id]);
        sort($names);

        $tools = [];
        foreach ($names as $name) {
            $info = external_api::external_function_info($name, IGNORE_MISSING);
            if (!$info || $name === 'local_kikursbauer_get_tools') {
                continue;
            }
            $tools[] = [
                'name' => $name,
                'description' => (string) $info->description,
                'inputSchema' => self::schema($info->parameters_desc),
            ];
        }
        return ['tools' => json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /**
     * Converts a Moodle parameter description into JSON schema.
     *
     * @param external_description $desc
     * @return array
     */
    protected static function schema(external_description $desc): array {
        if ($desc instanceof external_value) {
            $types = [PARAM_INT => 'integer', PARAM_FLOAT => 'number', PARAM_BOOL => 'boolean'];
            $schema = ['type' => $types[$desc->type] ?? 'string'];
            if ($desc->required == VALUE_DEFAULT && $desc->default !== null) {
                $schema['default'] = $desc->default;
            }
        } else if ($desc instanceof external_multiple_structure) {
            $schema = ['type' => 'array', 'items' => self::schema($desc->content)];
        } else if ($desc instanceof external_single_structure) {
            $properties = [];
            $required = [];
            foreach ($desc->keys as $key => $sub) {
                $properties[$key] = self::schema($sub);
                if ($sub->required == VALUE_REQUIRED) {
                    $required[] = $key;
                }
            }
            $schema = ['type' => 'object', 'properties' => $properties ?: new \stdClass()];
            if ($required) {
                $schema['required'] = $required;
            }
        } else {
            $schema = [];
        }
        if (!empty($desc->desc)) {
            $schema['description'] = (string) $desc->desc;
        }
        return $schema;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'tools' => new external_value(PARAM_RAW, 'JSON list of tools: name, description, inputSchema'),
        ]);
    }
}
