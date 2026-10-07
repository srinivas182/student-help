<?php

namespace App\Domains\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GatewayCredential extends Model
{
    use HasFactory;

    protected $fillable = ['channel', 'provider', 'credentials', 'is_active', 'verified_at', 'last_error'];

    /** Credentials never leave the server in readable form. */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function setSecrets(array $secrets): void
    {
        $this->credentials = Crypt::encryptString(json_encode($secrets));
    }

    public function secrets(): array
    {
        if (! $this->credentials) {
            return [];
        }

        return json_decode(Crypt::decryptString($this->credentials), true) ?? [];
    }

    /** Which fields are filled, without revealing any of them. */
    public function filledFields(): array
    {
        return collect($this->secrets())->map(fn ($value) => filled($value))->all();
    }
}
