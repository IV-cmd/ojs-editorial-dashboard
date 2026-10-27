<?php

namespace App\Enums;

enum Role: string
{
    // Reads everything, including which reviewer holds a review assignment.
    case Editor = 'editor';

    // Reads dashboard views and counts only; never sees reviewer-identifying fields.
    case Viewer = 'viewer';
}
