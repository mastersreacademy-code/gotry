<?php

namespace App\Http\Controllers;

use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParentChildController
{
    public function index(Request $request)
    {
        $children = Child::where('parent_id', $request->user()->id)->orderBy('first_name')->get();
        $activity = $request->validate(['activity' => ['nullable', 'integer', 'min:1']])['activity'] ?? null;

        return view('parent.children', compact('children', 'activity'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'first_name' => Str::squish((string) $request->input('first_name')),
            'surname' => Str::squish((string) $request->input('surname')),
        ]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:39'],
            'surname' => ['required', 'string', 'max:40'],
            'age' => ['required', 'integer', 'between:1,18'],
            'activity' => ['nullable', 'integer', 'min:1'],
        ]);
        $child = Child::firstOrCreate([
            'parent_id' => $request->user()->id,
            'registration_key' => hash('sha256', mb_strtolower($data['first_name'].' '.$data['surname'])),
        ], [
            'first_name' => $data['first_name'],
            'surname' => $data['surname'],
            'age' => $data['age'],
        ]);

        if (! empty($data['activity'])) {
            return redirect()->to(route('activities.show', $data['activity']).'#trial')
                ->withInput(['child_id' => $child->id]);
        }

        return redirect()->route('parent.children')->with('status', $child->wasRecentlyCreated ? 'Child added.' : 'This child is already in your account.');
    }
}
