<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActivityBrandingController
{
    public function update(Request $request, Activity $activity)
    {
        abort_unless($activity->provider_id === $request->user()->id, 404);
        $data = $request->validate([
            'logo' => ['required_without:background', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'background' => ['required_without:logo', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
        ]);

        $disk = Storage::disk('local');
        foreach (array_filter($data) as $kind => $image) {
            if ($disk->putFileAs('activity-branding/'.$activity->id, $image, $kind) === false) {
                return back()->withErrors(['branding' => 'The image could not be saved. Please try again.']);
            }
        }

        return back()->with('status', 'Activity images updated.');
    }

    public function show(Activity $activity, string $kind)
    {
        abort_unless($activity->status === 'pending_registrations', 404);
        $disk = Storage::disk('local');
        $path = 'activity-branding/'.$activity->id.'/'.$kind;
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
