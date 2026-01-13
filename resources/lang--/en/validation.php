<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The must be accepted.',
    'active_url' => 'The is not a valid URL.',
    'after' => 'The must be a date after :date.',
    'after_or_equal' => 'The must be a date after or equal to :date.',
    'alpha' => 'The may only contain letters.',
    'alpha_dash' => 'The may only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'The may only contain letters and numbers.',
    'array' => 'The must be an array.',
    'before' => 'The must be a date before :date.',
    'before_or_equal' => 'The must be a date before or equal to :date.',
    'between' => [
        'numeric' => 'The must be between :min and :max.',
        'file' => 'The must be between :min and :max kilobytes.',
        'string' => 'The must be between :min and :max characters.',
        'array' => 'The must have between :min and :max items.',
    ],
    'boolean' => 'The field must be true or false.',
    'confirmed' => 'The confirmation does not match.',
    'date' => 'The is not a valid date.',
    'date_equals' => 'The must be a date equal to :date.',
    'date_format' => 'The does not match the format :format.',
    'different' => 'The and :other must be different.',
    'digits' => 'The must be :digits digits.',
    'digits_between' => 'The must be between :min and :max digits.',
    'dimensions' => 'The has invalid image dimensions.',
    'distinct' => 'The field has a duplicate value.',
    'email' => 'The must be a valid email address.',
    'ends_with' => 'The must end with one of the following: :values.',
    'exists' => 'The selected is invalid.',
    'file' => 'The must be a file.',
    'filled' => 'The field must have a value.',
    'gt' => [
        'numeric' => 'The must be greater than :value.',
        'file' => 'The must be greater than :value kilobytes.',
        'string' => 'The must be greater than :value characters.',
        'array' => 'The must have more than :value items.',
    ],
    'gte' => [
        'numeric' => 'The must be greater than or equal :value.',
        'file' => 'The must be greater than or equal :value kilobytes.',
        'string' => 'The must be greater than or equal :value characters.',
        'array' => 'The must have :value items or more.',
    ],
    'image' => 'The must be an image.',
    'in' => 'The selected is invalid.',
    'in_array' => 'The field does not exist in :other.',
    'integer' => 'The must be an integer.',
    'ip' => 'The must be a valid IP address.',
    'ipv4' => 'The must be a valid IPv4 address.',
    'ipv6' => 'The must be a valid IPv6 address.',
    'json' => 'The must be a valid JSON string.',
    'lt' => [
        'numeric' => 'The must be less than :value.',
        'file' => 'The must be less than :value kilobytes.',
        'string' => 'The must be less than :value characters.',
        'array' => 'The must have less than :value items.',
    ],
    'lte' => [
        'numeric' => 'The must be less than or equal :value.',
        'file' => 'The must be less than or equal :value kilobytes.',
        'string' => 'The must be less than or equal :value characters.',
        'array' => 'The must not have more than :value items.',
    ],
    'max' => [
        'numeric' => 'The may not be greater than :max.',
        'file' => 'The may not be greater than :max kilobytes.',
        'string' => 'The may not be greater than :max characters.',
        'array' => 'The may not have more than :max items.',
    ],
    'mimes' => 'The must be a file of type: :values.',
    'mimetypes' => 'The must be a file of type: :values.',
    'min' => [
        'numeric' => 'The must be at least :min.',
        'file' => 'The must be at least :min kilobytes.',
        'string' => 'The must be at least :min characters.',
        'array' => 'The must have at least :min items.',
    ],
    'not_in' => 'The selected is invalid.',
    'not_regex' => 'The format is invalid.',
    'numeric' => 'The must be a number.',
    'password' => 'The password is incorrect.',
    'present' => 'The field must be present.',
    'regex' => 'The format is invalid.',
    'required' => 'The field is required.',
    'required_if' => 'The field is required when :other is :value.',
    'required_unless' => 'The field is required unless :other is in :values.',
    'required_with' => 'The field is required when :values is present.',
    'required_with_all' => 'The field is required when :values are present.',
    'required_without' => 'The field is required when :values is not present.',
    'required_without_all' => 'The field is required when none of :values are present.',
    'same' => 'The and :other must match.',
    'size' => [
        'numeric' => 'The must be :size.',
        'file' => 'The must be :size kilobytes.',
        'string' => 'The must be :size characters.',
        'array' => 'The must contain :size items.',
    ],
    'starts_with' => 'The must start with one of the following: :values.',
    'string' => 'The must be a string.',
    'timezone' => 'The must be a valid zone.',
    'unique' => 'The has already been taken.',
    'uploaded' => 'The failed to upload.',
    'url' => 'The format is invalid.',
    'uuid' => 'The must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
        'g-recaptcha-response' => [
            'required' => 'Please complete the reCAPTCHA challenge.',
            'captcha' => 'reCAPTCHA verification failed. Please try again.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
