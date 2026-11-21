<?php

namespace App\Http\Requests\Amc;

use Illuminate\Foundation\Http\FormRequest;

/** Replace an AMC employee's photo (Employee master or the contract page). Who may do it is checked by the route. */
class AmcPhotoRequest extends FormRequest
{
    public function rules(): array
    {
        return ['photo' => AmcEmployeeRequest::photoRules(true)];
    }

    public function messages(): array
    {
        return ['photo.required' => 'Choose a photo.'] + (new AmcEmployeeRequest())->messages();
    }
}
