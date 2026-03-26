<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'account_type' => $this->account_type,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'lines' => LineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
