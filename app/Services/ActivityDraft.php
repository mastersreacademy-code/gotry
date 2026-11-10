<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ActivityDraft
{
    public const STEPS = [1 => 'Business', 2 => 'Activity', 3 => 'Trial Lesson', 4 => 'Usual Lessons', 5 => 'Review'];
    public const CATEGORIES = ['Sports & Fitness', 'Music', 'Dance', 'Arts & Crafts', 'STEM & Coding', 'Drama & Performance', 'Languages', 'Other'];

    public function fields(int $step): array
    {
        // name, label, input type, options, optional controlling field/value.
        return match ($step) {
            1 => [
                ['business_name', 'Business or organisation name', 'text'],
                ['abn', 'ABN (optional)', 'text'], ['website', 'Business website (optional)', 'url'],
                ['contact_name', "Contact person's full name", 'text'],
                ['phone', 'Mobile number', 'tel'], ['contact_email', 'Contact email', 'email'],
            ],
            2 => [
                ['activity_name', 'Activity title', 'text'], ['category', 'Category', 'select', array_combine(self::CATEGORIES, self::CATEGORIES)],
                ['min_age', 'Minimum age (years)', 'number'], ['max_age', 'Maximum age (years)', 'number'],
                ['description', 'Activity description', 'textarea'], ['venue', 'Venue / suburb', 'text'],
                ['bring', 'What should children bring? (optional)', 'text'],
            ],
            3 => [
                ['trial_type', 'Trial lesson format', 'select', ['group' => 'Group trial', 'individual' => 'Individual trial']],
                ['trial_date', 'Proposed trial date', 'date', [], ['trial_type', 'group']],
                ['trial_time', 'Trial start time (Sydney time)', 'time', [], ['trial_type', 'group']],
                ['trial_min', 'Minimum participants to run', 'number', [], ['trial_type', 'group']],
                ['trial_max', 'Maximum trial participants', 'number', [], ['trial_type', 'group']],
                ['trial_availability', 'Trial availability', 'textarea', [], ['trial_type', 'individual']],
                ['trial_duration', 'Trial duration (minutes)', 'number'],
                ['deadline', 'Last date to register interest', 'date'],
            ],
            4 => [
                ['regular_format', 'Regular lesson format', 'select', ['group' => 'Group lessons', 'individual' => 'Individual lessons']],
                ['multiple_days', 'Multiple class days / times?', 'select', ['0' => 'No — one weekly time', '1' => 'Yes — several times'], ['regular_format', 'group']],
                ['regular_day', 'Usual class day', 'select', array_combine(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']), ['multiple_days', '0']],
                ['regular_time', 'Start time', 'time', [], ['multiple_days', '0']],
                ['class_times', 'Class days and times', 'textarea', [], ['multiple_days', '1']],
                ['regular_min', 'Minimum small-group size', 'number', [], ['regular_format', 'group']],
                ['regular_standard', 'Standard group starts at', 'number', [], ['regular_format', 'group']],
                ['regular_max', 'Maximum regular group size', 'number', [], ['regular_format', 'group']],
                ['standard_price', 'Standard price ($ per lesson)', 'number', [], ['regular_format', 'group']],
                ['small_group_price', 'Small-group price ($ per lesson)', 'number', [], ['regular_format', 'group']],
                ['lessons_per_term', 'Number of lessons per term', 'number', [], ['regular_format', 'group']],
                ['usual_availability', 'Usual availability', 'textarea', [], ['regular_format', 'individual']],
                ['individual_price', 'Price per individual lesson ($)', 'number', [], ['regular_format', 'individual']],
                ['discount_type', 'Exclusive GoTry discount', 'select', ['none' => 'No discount', 'percent' => 'Percentage (%)', 'fixed' => 'Fixed amount ($)']],
                ['discount_value', 'Discount value', 'number'],
                ['discount_applies', 'Discount applies to the first', 'select', ['term' => 'Term', 'lesson' => 'Lesson']],
            ],
            default => [],
        };
    }

    public function validate(int $step, array $input): array
    {
        $today = CarbonImmutable::now('Australia/Sydney')->toDateString();
        $money = ['numeric', 'decimal:0,2', 'min:0', 'max:100000'];
        $group = ($input['regular_format'] ?? null) === 'group';
        $singleTime = $group && (string) ($input['multiple_days'] ?? '0') === '0';
        $rules = match ($step) {
            1 => [
                'business_name' => ['required', 'string', 'max:160'], 'contact_name' => ['required', 'string', 'max:100'],
                'contact_email' => ['required', 'email', 'max:254'], 'phone' => ['required', 'string', 'max:40', 'regex:/^[+()0-9 .-]{6,40}$/'],
                'website' => ['nullable', 'url:http,https', 'max:500'], 'abn' => ['nullable', 'regex:/^[0-9]{11}$/'],
            ],
            2 => [
                'activity_name' => ['required', 'string', 'max:160'], 'category' => ['required', Rule::in(self::CATEGORIES)],
                'min_age' => ['required', 'integer', 'between:1,18'], 'max_age' => ['required', 'integer', 'between:1,18', 'gte:min_age'],
                'description' => ['required', 'string', 'max:5000'], 'venue' => ['required', 'string', 'max:300'], 'bring' => ['nullable', 'string', 'max:1000'],
            ],
            3 => [
                'trial_type' => ['required', Rule::in(['group', 'individual'])],
                'trial_date' => ['exclude_unless:trial_type,group', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
                'trial_time' => ['exclude_unless:trial_type,group', 'required', 'date_format:H:i'],
                'trial_min' => ['exclude_unless:trial_type,group', 'required', 'integer', 'between:1,100'],
                'trial_max' => ['exclude_unless:trial_type,group', 'required', 'integer', 'between:1,100', 'gte:trial_min'],
                'trial_availability' => ['exclude_unless:trial_type,individual', 'required', 'string', 'max:2000'],
                'trial_duration' => ['required', 'integer', 'between:5,240'],
                'deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            ],
            4 => [
                'regular_format' => ['required', Rule::in(['group','individual'])],
                'multiple_days' => ['exclude_unless:regular_format,group', 'required', Rule::in(['0','1'])],
                'regular_day' => [Rule::excludeIf(! $singleTime), 'required', Rule::in(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'])],
                'regular_time' => [Rule::excludeIf(! $singleTime), 'required', 'date_format:H:i'],
                'class_times' => [Rule::excludeIf(! $group || $singleTime), 'required', 'string', 'max:2000'],
                'regular_min' => ['exclude_unless:regular_format,group', 'required', 'integer', 'between:1,100'],
                'regular_standard' => ['exclude_unless:regular_format,group', 'required', 'integer', 'between:1,100', 'gte:regular_min'],
                'regular_max' => ['exclude_unless:regular_format,group', 'required', 'integer', 'between:1,100', 'gte:regular_standard'],
                'standard_price' => ['exclude_unless:regular_format,group', 'required', ...$money],
                'small_group_price' => ['exclude_unless:regular_format,group', 'required', ...$money],
                'lessons_per_term' => ['exclude_unless:regular_format,group', 'required', 'integer', 'between:1,100'],
                'usual_availability' => ['exclude_unless:regular_format,individual', 'required', 'string', 'max:2000'],
                'individual_price' => ['exclude_unless:regular_format,individual', 'required', ...$money],
                'discount_type' => ['required', Rule::in(['none','percent','fixed'])],
                'discount_value' => ['exclude_if:discount_type,none', 'required', ...$money],
                'discount_applies' => ['exclude_if:discount_type,none', 'required', Rule::in(['term','lesson'])],
            ],
            default => [],
        };
        $validator = Validator::make($input, $rules);
        $validator->after(function ($validator) use ($step, $input): void {
            if ($validator->errors()->isNotEmpty()) return;
            if ($step === 3 && $input['trial_type'] === 'group') {
                if ($input['deadline'] > $input['trial_date']) $validator->errors()->add('deadline', 'The deadline must be on or before the trial date.');
                $local = $input['trial_date'].' '.$input['trial_time'];
                $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, 'Australia/Sydney');
                if ($start->format('Y-m-d H:i') !== $local || $start->isPast()) {
                    $validator->errors()->add('trial_time', 'Choose a valid future Sydney date and time.');
                }
                // Sydney's repeated autumn hour is ambiguous without an explicit offset.
                foreach ([-3600, 3600] as $offset) {
                    if ($start->addSeconds($offset)->format('Y-m-d H:i') === $local) {
                        $validator->errors()->add('trial_time', 'Choose a time outside the Sydney daylight-saving transition.');
                    }
                }
            }
            if ($step === 4 && $input['discount_type'] !== 'none') {
                $pricing = new ProgrammePricing;
                if ($input['discount_type'] === 'percent' && $pricing->cents($input['discount_value']) > 10000) {
                    $validator->errors()->add('discount_value', 'Percentage discounts cannot exceed 100%.');
                }
                if ($input['discount_type'] === 'fixed') {
                    $individual = $input['regular_format'] === 'individual';
                    $lessons = $individual || $input['discount_applies'] === 'lesson' ? 1 : (int) $input['lessons_per_term'];
                    $base = $individual ? $pricing->cents($input['individual_price']) : min($pricing->cents($input['standard_price']), $pricing->cents($input['small_group_price']));
                    if ($pricing->cents($input['discount_value']) > $base * $lessons) $validator->errors()->add('discount_value', 'The discount cannot exceed the applicable lesson or term price.');
                }
            }
        });
        return $validator->validate();
    }
}
