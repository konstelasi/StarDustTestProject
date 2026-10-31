<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use StarDust\Slot\SlotReserver;
use StarDust\StarDust;

class CustomFieldController extends Controller
{
    public function __construct(private readonly StarDust $stardust) {}

    public function index()
    {
        $fields = CustomFieldDefinition::with('user')->latest()->get();
        $users = User::orderBy('name')->get();

        return view('admin.custom_fields.index', compact('fields', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'type' => 'required|string|in:string,text,integer,number,date,select,boolean',
            'options' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'is_required' => 'nullable|boolean',
        ]);

        $name = Str::slug($validated['label'], '_');

        if (empty($name)) {
            $name = 'custom_'.time();
        }

        // Prevent exact duplicate slugs
        $count = CustomFieldDefinition::where('name', $name)->count();
        if ($count > 0) {
            $name .= '_'.($count + 1);
        }

        $cdef = CustomFieldDefinition::create([
            'name' => $name,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'user_id' => ! empty($validated['user_id']) ? (int) $validated['user_id'] : null,
            'is_required' => $request->boolean('is_required'),
        ]);

        // Register custom field to StarDust Engine & reserve slot
        try {
            $tenantId = Tenant::id();
            $itemModelName = config('stardust.item_model_name', 'barang');
            $models = $this->stardust->listModels($tenantId);
            $barangModel = collect($models)->firstWhere('name', $itemModelName);

            if ($barangModel) {
                $declaredType = match (strtolower(trim($validated['type']))) {
                    'number', 'numeric', 'float', 'double' => 'numeric',
                    'integer', 'int', 'boolean', 'bool' => 'int',
                    'date', 'datetime' => 'datetime',
                    default => 'string',
                };

                $fieldId = $this->stardust->schemaBuilder()->defineField(
                    modelId: $barangModel->modelId,
                    name: $name,
                    declaredType: $declaredType,
                    isFilterable: true
                );

                $reserver = new SlotReserver(
                    $this->stardust->pdo(),
                    $this->stardust->config()->clock,
                    $this->stardust->logger()
                );
                $reserver->reserve($fieldId);
            }
        } catch (\Throwable $e) {
            // Ignore if field already exists or slot reservation fails
        }

        $scopeMsg = ! empty($validated['user_id']) ? 'khusus untuk User/Staff tertentu' : 'secara Global (Semua User)';

        return redirect()->route('admin.custom-fields.index')
            ->with('success', "Atribut Dinamis '{$validated['label']}' berhasil ditambahkan {$scopeMsg}!");
    }

    public function destroy($id)
    {
        $field = CustomFieldDefinition::findOrFail($id);
        $label = $field->label;
        $field->delete();

        return redirect()->route('admin.custom-fields.index')
            ->with('success', "Atribut Dinamis '{$label}' berhasil dihapus.");
    }
}
