<?php
declare(strict_types=1);
/**
 * RoleController.php
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

namespace App\Http\Controllers\Import;


use App\Http\Controllers\Controller;
use App\Http\Middleware\RolesComplete;
use App\Http\Request\RolesPostRequest;
use App\Services\CSV\Configuration\Configuration;
use App\Services\CSV\Roles\RoleService;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use League\Csv\Exception;
use Log;

/**
 * Class RoleController
 */
class RoleController extends Controller
{
        /**
         * RoleController constructor.
         */
        public function __construct()
        {
            parent::__construct();
            app('view')->share('pageTitle', 'Define roles');
            $this->middleware(RolesComplete::class);
        }

    /**
     * @throws Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @return Factory|View
     */
    public function index()
    {
        Log::debug('Now in role controller');
        $mainTitle = 'Define roles';
        $subTitle  = 'Configure the role of each column in your file';

        // get configuration object.
        $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));

        // get columns from file
        $content  = StorageService::getContent(session()->get(Constants::UPLOAD_CSV_FILE));
        $columns  = RoleService::getColumns($content, $configuration);
        $examples = RoleService::getExampleData($content, $configuration);

        // submit mapping from config.
        $mapping = base64_encode(json_encode($configuration->getMapping(), JSON_THROW_ON_ERROR, 512));

        // roles
        $roles = config('csv_importer.import_roles');
        ksort($roles);

        // configuration (if it is set)
        $configuredRoles     = $configuration->getRoles();
        $configuredDoMapping = $configuration->getDoMapping();

        return view(
            'import.roles.index',
            compact('mainTitle','configuration', 'subTitle', 'columns', 'examples', 'roles', 'configuredRoles', 'configuredDoMapping', 'mapping')
        );
    }

    /**
     * @param RolesPostRequest $request
     *
     * @return RedirectResponse
     */
    public function postIndex(RolesPostRequest $request): RedirectResponse
    {
        $data = $request->getAll();

        // get configuration object.
        $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        $needsMapping  = $this->needMapping($data['do_mapping']);
        $configuration->setRoles($data['roles']);
        $configuration->setDoMapping($data['do_mapping']);

        session()->put(Constants::CONFIGURATION, $configuration->toArray());

        // set role config as complete.
        session()->put(Constants::ROLES_COMPLETE_INDICATOR, true);

        // redirect to mapping thing.
        if (true === $needsMapping) {
            return redirect()->route('import.mapping.index');
        }
        // otherwise, store empty mapping, and continue:
        // set map config as complete.
        session()->put(Constants::MAPPING_COMPLETE_INDICATOR, true);

        return redirect()->route('import.run.index');
    }

    /**
     * Will tell you if any role needs mapping.
     *
     * @param array $array
     *
     * @return bool
     */
    private function needMapping(array $array): bool
    {
        $need = false;
        foreach ($array as $value) {
            if (true === $value) {
                $need = true;
            }
        }

        return $need;
    }
}
