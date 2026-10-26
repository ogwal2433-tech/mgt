<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalParty extends Model
{
    protected $fillable = ['name', 'type', 'contact_person', 'email', 'phone', 'address', 'tax_number', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(ExternalRequisition::class);
    }
}
