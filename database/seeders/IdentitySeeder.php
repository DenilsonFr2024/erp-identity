<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Aplicações
        $website = Application::create([
            'code' => 'website',
            'name' => 'Site Oficial Ulimbo Tech',
        ]);
        $erp = Application::create([
            'code' => 'erp',
            'name' => 'Ulimbo ERP',
        ]);

        // 2. Roles
        $roles = [
            'ulimbo_admin'     => 'Administrador Ulimbo',
            'ulimbo_sales'     => 'Vendas Ulimbo',
            'ulimbo_finance'   => 'Finanças Ulimbo',
            'ulimbo_hr'        => 'RH Ulimbo',
            'ulimbo_marketing' => 'Marketing Ulimbo',
            'partner_erp'      => 'Parceiro ERP',
            'client_contact'   => 'Contacto Cliente',
        ];
        foreach ($roles as $name => $label) {
            Role::create(['name' => $name]);
        }

        // 3. Permissões base
        $permissions = [
            ['application_id' => $erp->application_id, 'code' => 'erp.admin', 'name' => 'Admin ERP'],
            ['application_id' => $erp->application_id, 'code' => 'erp.sales.view', 'name' => 'Ver Vendas'],
            ['application_id' => $erp->application_id, 'code' => 'erp.sales.edit', 'name' => 'Editar Vendas'],
            ['application_id' => $erp->application_id, 'code' => 'erp.finance.view', 'name' => 'Ver Finanças'],
            ['application_id' => $erp->application_id, 'code' => 'erp.finance.edit', 'name' => 'Editar Finanças'],
            ['application_id' => $erp->application_id, 'code' => 'erp.hr.view', 'name' => 'Ver RH'],
            ['application_id' => $erp->application_id, 'code' => 'erp.hr.edit', 'name' => 'Editar RH'],
            ['application_id' => $erp->application_id, 'code' => 'erp.partner', 'name' => 'Acesso Parceiro'],
            ['application_id' => $erp->application_id, 'code' => 'erp.client', 'name' => 'Acesso Cliente'],
            ['application_id' => $website->application_id, 'code' => 'website.cms.edit', 'name' => 'Editar CMS'],
        ];
        foreach ($permissions as $p) {
            Permission::create($p);
        }

        // 4. Atribuir permissões a roles
        $assign = [
            'ulimbo_admin'   => ['erp.admin', 'erp.sales.view', 'erp.sales.edit', 'erp.finance.view', 'erp.finance.edit', 'erp.hr.view', 'erp.hr.edit', 'website.cms.edit'],
            'ulimbo_sales'   => ['erp.sales.view', 'erp.sales.edit'],
            'ulimbo_finance' => ['erp.finance.view', 'erp.finance.edit'],
            'ulimbo_hr'      => ['erp.hr.view', 'erp.hr.edit'],
            'ulimbo_marketing' => ['website.cms.edit'],
            'partner_erp'    => ['erp.partner'],
            'client_contact' => ['erp.client'],
        ];
        foreach ($assign as $roleName => $permissionCodes) {
            $role = Role::where('name', $roleName)->first();
            foreach ($permissionCodes as $code) {
                $perm = Permission::where('code', $code)->first();
                RolePermission::create([
                    'role_id' => $role->role_id,
                    'permission_id' => $perm->permission_id,
                ]);
            }
        }
    }
}
