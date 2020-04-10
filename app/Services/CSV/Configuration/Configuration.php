<?php
declare(strict_types=1);
/**
 * Configuration.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Services\CSV\Configuration;

use App\Services\CSV\Specifics\SpecificService;
use Log;
use RuntimeException;

/**
 * Class Configuration
 */
class Configuration
{
    /** @var string */
    private $date;
    /** @var int */
    private $defaultAccount;
    /** @var string */
    private $delimiter;
    /** @var bool */
    private $headers;
    /** @var bool When set to true, the importer will ignore existing duplicate transactions found in Firefly III. */
    private $ignoreDuplicateTransactions;
    /** @var bool When set to true, the importer will ignore duplicate lines in the CSV file. */
    private $ignoreDuplicateLines;
    /** @var bool */
    private $rules;
    /** @var bool */
    private $skipForm;
    /** @var array */
    private $specifics;
    /** @var array */
    private $roles;
    /** @var int */
    private $version;

    /** @var array */
    private $doMapping;

    /** @var array */
    private $mapping;
    /** @var int */
    public const VERSION = 2;


    /**
     * Configuration constructor.
     */
    private function __construct()
    {
        $this->date                        = 'Y-m-d';
        $this->defaultAccount              = 1;
        $this->delimiter                   = 'comma';
        $this->headers                     = false;
        $this->ignoreDuplicateTransactions = true;
        $this->ignoreDuplicateLines        = true;
        $this->rules                       = true;
        $this->skipForm                    = false;
        $this->specifics                   = [];
        $this->roles                       = [];
        $this->mapping                     = [];
        $this->doMapping                   = [];
        $this->version                     = self::VERSION;
    }

    /**
     * @return bool
     */
    public function isSkipForm(): bool
    {
        return $this->skipForm;
    }



    /**
     * @param array $array
     *
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $version = $array['version'] ?? 1;

        // TODO now have room to do version based array parsing.

        $object                              = new self;
        $object->headers                     = $array['headers'];
        $object->date                        = $array['date'];
        $object->defaultAccount              = $array['default_account'];
        $object->delimiter                   = $array['delimiter'];
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'];
        $object->ignoreDuplicateTransactions = $array['ignore_duplicate_transactions'];
        $object->rules                       = $array['rules'];
        $object->skipForm                    = $array['skip_form'];
        $object->specifics                   = $array['specifics'];
        $object->roles                       = $array['roles'];
        $object->mapping                     = $array['mapping'];
        $object->doMapping                   = $array['do_mapping'];
        $object->version                     = $version;

        return $object;
    }


    /**
     * @param array $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @param array $doMapping
     */
    public function setDoMapping(array $doMapping): void
    {
        $this->doMapping = $doMapping;
    }

    /**
     * @param array $mapping
     */
    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles ?? [];
    }

    /**
     * @param array $array
     *
     * @return $this
     */
    public static function fromRequest(array $array): self
    {
        $object                              = new self;
        $object->version                     = self::VERSION;
        $object->headers                     = $array['headers'];
        $object->date                        = $array['date'];
        $object->defaultAccount              = $array['default_account'];
        $object->delimiter                   = $array['delimiter'];
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'];
        $object->ignoreDuplicateTransactions = $array['ignore_duplicate_transactions'];
        $object->rules                       = $array['rules'];
        $object->skipForm                    = $array['skip_form'];
        $object->specifics                   = $array['specifics'];
        $object->roles                       = $array['roles'];
        $object->mapping                     = $array['mapping'];
        $object->doMapping                   = $array['do_mapping'];

        return $object;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    private static function fromClassicFile(array $data): self
    {
        $delimiters             = config('csv_importer.delimiters');
        $classicRoleNames       = config('csv_importer.classic_roles');
        $object                 = new self;
        $object->headers        = $data['has-headers'] ?? false;
        $object->date           = $data['date-format'] ?? $object->date;
        $object->delimiter      = $delimiters[$data['delimiter'] ?? ','];
        $object->defaultAccount = $data['import-account'] ?? $object->defaultAccount;
        $object->rules          = $data['apply-rules'] ?? true;

        // array values
        $object->specifics = [];
        $object->roles     = [];
        $object->doMapping = [];
        $object->mapping   = [];

        // loop specifics from classic file:
        $specifics = array_keys($data['specifics'] ?? []);
        foreach ($specifics as $name) {
            $class = SpecificService::fullClass($name);
            if (class_exists($class)) {
                $object->specifics[$name] = 1;
            }
        }

        // loop roles from classic file:
        $roles = $data['column-roles'] ?? [];
        foreach ($roles as $role) {
            // some roles have been given a new name some time in the past.
            $role = $classicRoleNames[$role] ?? $role;

            $config = config(sprintf('csv_importer.import_roles.%s', $role));
            if (null !== $config) {
                $object->roles[] = $role;
            }
        }

        // loop do mapping from classic file.
        $doMapping = $data['column-do-mapping'] ?? [];
        foreach ($doMapping as $index => $map) {
            $index                     = (int)$index;
            $object->doMapping[$index] = $map;
        }

        // loop mapping from classic file.
        $mapping = $data['column-mapping-config'] ?? [];
        foreach ($mapping as $index => $map) {
            $index                   = (int)$index;
            $object->mapping[$index] = $map;
        }
        // set version to "2" and return.
        $object->version = 2;

        return $object;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public static function fromFile(array $data): self
    {
        Log::debug('Now in Configuration::fromClassic', $data);
        $version = $data['version'] ?? 1;
        if (1 === $version) {
            return self::fromClassicFile($data);
        }
        if (2 === $version) {
            return self::fromVersionTwo($data);
        }
        throw new RuntimeException(sprintf('Configuration file version "%s" cannot be parsed.', $version));
    }

    /**
     * @return bool
     */
    public function isIgnoreDuplicateLines(): bool
    {
        return $this->ignoreDuplicateLines;
    }

    /**
     * @return bool
     */
    public function isIgnoreDuplicateTransactions(): bool
    {
        return $this->ignoreDuplicateTransactions;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    private static function fromVersionTwo(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'date'                          => $this->date,
            'default_account'               => $this->defaultAccount,
            'delimiter'                     => $this->delimiter,
            'headers'                       => $this->headers,
            'ignore_duplicate_lines'        => $this->ignoreDuplicateLines,
            'ignore_duplicate_transactions' => $this->ignoreDuplicateTransactions,
            'rules'                         => $this->rules,
            'skip_form'                     => $this->skipForm,
            'specifics'                     => $this->specifics,
            'roles'                         => $this->roles,
            'do_mapping'                    => $this->doMapping,
            'mapping'                       => $this->mapping,
            'version' => $this->version,
        ];
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * @return int
     */
    public function getDefaultAccount(): ?int
    {
        return $this->defaultAccount;
    }

    /**
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasSpecific(string $name): bool
    {
        return in_array($name, $this->specifics, true);
    }

    /**
     * @return bool
     */
    public function isHeaders(): bool
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getDoMapping(): array
    {
        return $this->doMapping ?? [];
    }

    /**
     * @return array
     */
    public function getMapping(): array
    {
        return $this->mapping ?? [];
    }

    /**
     * @return array
     */
    public function getSpecifics(): array
    {
        return $this->specifics;
    }


}
