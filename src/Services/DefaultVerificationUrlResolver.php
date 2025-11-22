<?php

namespace LBHurtado\HyperVerge\Services;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\HyperVerge\Contracts\VerificationUrlResolver;

class DefaultVerificationUrlResolver implements VerificationUrlResolver
{
    /**
     * Resolve verification URL for a signed document.
     * 
     * Default implementation assumes a route named 'document.verify'
     * that accepts the model's route key.
     */
    public function resolve(Model $model, ?string $transactionId = null): string
    {
        // Try to use a named route if it exists
        if (route()->has('document.verify')) {
            return route('document.verify', $model->getRouteKey());
        }

        // Fallback: Use model class and key
        $class = class_basename($model);
        $key = $model->getRouteKey();

        return url("/verify/{$class}/{$key}");
    }
}
