<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class SocialBulkImport extends Model {

    protected $table = 'social_bulk_imports';

    protected $fillable = [
        'original_name', 'path', 'status', 'total_rows', 'valid_rows',
        'invalid_rows', 'created_posts', 'options', 'error', 'created_by',
    ];

    protected $casts = ['options' => 'array'];

    public function rows() {
        return $this->hasMany(SocialBulkImportRow::class, 'social_bulk_import_id');
    }

    public function getStatusClassAttribute(): string {
        return match ($this->status) {
            'completed'  => 'success',
            'processing' => 'primary',
            'failed'     => 'danger',
            'cancelled'  => 'dark',
            'previewed'  => 'info',
            default      => 'secondary',
        };
    }
}
