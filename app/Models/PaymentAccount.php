<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class PaymentAccount extends Model
{
    //
    use HasFactory, HasApiTokens;
    protected $table = 'payment_account';
    protected $fillable = [
        'user_id',
        'title',
        'account_title',
        'bank_name',
        'account_number',
        'iban',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
