<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\BrandingThemes;
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\TravelName;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BrandingThemeController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $travels = TravelName::get();
        return view('admin.transaksi.sales.branding_theme', compact('travels'));
    }

    // GET: daftar tema untuk tabel
    public function list()
    {
        $data = BrandingThemes::with('travel:id,name,location_path_image')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $t->logo_url = $this->travelLogoUrl($t->travel);
                return $t;
            });

        return $this->autoResponse($data);
    }

    private function travelLogoUrl($travel): ?string
    {
        return $travel && $travel->location_path_image
            ? asset($travel->location_path_image)
            : null;
    }
    // GET: satu tema untuk form edit
    public function show($id)
    {
        $theme = BrandingThemes::with('travel:id,name,address,location_path_image')->find($id);
        if (!$theme) {
            return $this->error('Tema tidak ditemukan', 404);
        }

        $theme->logo_url = $this->travelLogoUrl($theme->travel);

        return $this->autoResponse($theme);
    }

    // POST: tambah
    public function store(Request $request)
    {
        return $this->save($request, null);
    }

    // POST: ubah
    public function update(Request $request, $id)
    {
        return $this->save($request, $id);
    }

    public function travelLogo($id)
    {
        $travel = TravelName::find($id, ['id', 'location_path_image']);
        if (!$travel) {
            return $this->error('Travel tidak ditemukan', 404);
        }

        return $this->autoResponse([
            'logo_url' => $this->travelLogoUrl($travel),
        ]);
    }
    private function save(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'travel_id' => 'required|integer|exists:travel_names,id',
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bank_accounts' => 'required|string',
            'terms' => 'nullable|string',
            'signature_name' => 'required|string|max:191',
            'signature_title' => 'required|string|max:191',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $theme = $id ? BrandingThemes::find($id) : new BrandingThemes();
            if ($id && !$theme) {
                throw new \RuntimeException('Tema tidak ditemukan');
            }

            $theme->fill([
                'name' => $request->name,
                'travel_id' => $request->travel_id,
                'primary_color' => $request->primary_color,
                'bank_accounts' => $request->bank_accounts,
                'terms' => $request->terms,
                'signature_name' => $request->signature_name,
                'signature_title' => $request->signature_title,
                'is_default' => (bool) $request->is_default,
            ]);

            if (!$theme->exists) {
                $theme->created_by = optional($request->user_login)->id;
            }

            $theme->save();

            if ($theme->is_default) {
                BrandingThemes::where('id', '!=', $theme->id)->update(['is_default' => false]);
            }

            DB::commit();

            return $this->success($theme, 'Tema berhasil disimpan');

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage(), 422);
        }
    }

    // POST: hapus
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:branding_themes,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $theme = BrandingThemes::find($request->id);

        // Tema yang sedang dipakai invoice tidak boleh dihapus
        $used = InvoicesAllFromXero::where('branding_theme_id', $theme->id)->count();
        if ($used > 0) {
            return $this->error("Tema dipakai oleh {$used} invoice, tidak bisa dihapus.", 422);
        }

        if ($theme->logo_path) {
            Storage::disk('public')->delete($theme->logo_path);
        }

        $theme->delete();

        return $this->success(null, 'Tema berhasil dihapus');
    }
}