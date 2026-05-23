<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\LandingEcosystem;
use Illuminate\Http\Request;

class EcosystemController extends Controller
{
    public function index()
    {
        $detail = LandingEcosystem::where('type', 'header')->first();
        $ecosystems = LandingEcosystem::where('type', 'card')->get();
        return view('Backend.landing-page.ecosystem', compact('detail', 'ecosystems'));
    }

    public function store(Request $request)
    {
        if ($request->has('update_detail')) {
            LandingEcosystem::updateOrCreate(
                ['type' => 'header'],
                [
                    'title'       => $request->title,
                    'description' => $request->description,
                ]
            );
            return back()->with('status', 'Header updated successfully!');
        }

        // Handle cards update
        if ($request->has('card_id')) {
            foreach ($request->card_id as $key => $id) {
                if ($id == 'new') {
                    if (!empty($request->title[$key])) {
                        LandingEcosystem::create([
                            'title'       => $request->title[$key],
                            'description' => $request->description[$key] ?? '',
                            'type'        => 'card',
                        ]);
                    }
                } else {
                    LandingEcosystem::where('id', $id)->update([
                        'title'       => $request->title[$key],
                        'description' => $request->description[$key] ?? '',
                    ]);
                }
            }
            return back()->with('status', 'Ecosystem cards updated successfully!');
        }

        return back();
    }

    public function destroy($id)
    {
        LandingEcosystem::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}

