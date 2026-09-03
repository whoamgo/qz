<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class SocialBulkImportRow extends Model {

    protected $table = 'social_bulk_import_rows';

    protected $fillable = [
        'social_bulk_import_id', 'row_number', 'data', 'errors', 'status', 'social_post_id',
    ];

    protected $casts = [
        'data'   => 'array',
        'errors' => 'array',
    ];

    public function import() {
        return $this->belongsTo(SocialBulkImport::class, 'social_bulk_import_id');
    }

    public function post() {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function getStatusClassAttribute(): string {
        return match ($this->status) {
            'valid', 'imported' => 'success',
            'invalid'           => 'danger',
            'skipped'           => 'secondary',
            default             => 'info',
        };
    }
}
