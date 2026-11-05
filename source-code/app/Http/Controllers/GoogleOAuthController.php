<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class GoogleOAuthController extends Controller
{
    public function authorizeGoogle()
    {
        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => url('/google/callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/gmail.send',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(\Illuminate\Http\Request $request)
    {
        abort_if($request->filled('error'), 400, $request->string('error')->toString());

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->string('code')->toString(),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => url('/google/callback'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        return response(
            '<h2>Google authorization successful</h2><p>Add this value to Render as <code>GOOGLE_REFRESH_TOKEN</code>, then remove or protect this route:</p><textarea style="width:100%;height:100px">'.e($response['refresh_token'] ?? 'No refresh token returned; authorize again with prompt=consent.').'</textarea>'
        );
    }
}
