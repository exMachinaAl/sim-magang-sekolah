<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificatePrintTemplateDefault extends Model
{
    protected $fillable = ['major', 'certificate_print_template_id', 'updated_by'];

    public function template()
    {
        return $this->belongsTo(CertificatePrintTemplate::class, 'certificate_print_template_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
