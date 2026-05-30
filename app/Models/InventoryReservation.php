<?php
 
namespace App\Models;
 
use App\Models\Concerns\BelongsToRestaurant;
 use Illuminate\Database\Eloquent\Factories\HasFactory;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
 class InventoryReservation extends Model
 {
     use BelongsToRestaurant;
     use HasFactory;
 
     protected $guarded = [];
 
     public function order(): BelongsTo
     {
         return $this->belongsTo(Order::class);
     }
 
     public function ingredient(): BelongsTo
     {
         return $this->belongsTo(Ingredient::class);
     }
 }
