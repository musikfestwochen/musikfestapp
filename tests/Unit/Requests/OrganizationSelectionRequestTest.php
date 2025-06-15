<?php

use App\Http\Requests\OrganizationSelectionRequest;

covers(OrganizationSelectionRequest::class);

beforeEach(function () {
    $this->request = new OrganizationSelectionRequest;
});

it('has required methods', function () {
    // Test that the request class has the required methods
    expect(method_exists($this->request, 'rules'))->toBeTrue();
    expect(method_exists($this->request, 'authorize'))->toBeTrue();
});

it('extends FormRequest', function () {
    // Test that the request properly extends FormRequest
    expect($this->request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

it('authorizes all authenticated users', function () {
    expect($this->request->authorize())->toBeTrue();
});
