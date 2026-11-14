<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'price' => $this->price,
            'price_negotiable' => $this->price_negotiable,
            'price_note' => $this->price_note,
            'status' => $this->status,
            'visible_in_dashboard' => $this->visible_in_dashboard,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
