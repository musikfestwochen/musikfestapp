<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;

class AppearanceController extends Controller
{
    /**
     * Update user's appearance-related settings.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'eastereggs_activated' => (bool) $request->boolean('eastereggs_activated'),
        ]);

        return back()->with('status', 'Appearance settings updated.');
    }
}
