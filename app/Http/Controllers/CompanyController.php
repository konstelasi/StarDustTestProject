<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\Tenant;
use App\Support\TenantProvisioner;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private readonly TenantProvisioner $provisioner) {}

    public function index()
    {
        $companies = Company::withCount('users')->orderBy('name')->get();
        $activeId = Tenant::id();

        return view('companies.index', compact('companies', 'activeId'));
    }

    public function store(Request $request)
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:companies,code',
        ]);

        $company = Company::create($validated);

        try {
            $this->provisioner->provision($company->id);
        } catch (\Throwable $e) {
            $company->delete();

            return back()->withInput()
                ->with('error', 'Gagal menyiapkan data StarDust: '.$e->getMessage());
        }

        return redirect()->route('companies.index')
            ->with('success', "Perusahaan '{$company->name}' berhasil dibuat.");
    }

    public function select(Company $company)
    {
        session([Tenant::SESSION_KEY => $company->id]);
        session()->forget('active_warehouse');

        return redirect()->route('inventory.index')
            ->with('success', "Perusahaan aktif: {$company->name}");
    }
}
