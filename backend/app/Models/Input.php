<?php

namespace App\Models;

use Database\Factories\InputFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'unit'])]
class Input extends Model
{
    /** @use HasFactory<InputFactory> */
    use HasFactory;

    public function usages(): HasMany
    {
        return $this->hasMany(InputUsage::class);
    }
}
