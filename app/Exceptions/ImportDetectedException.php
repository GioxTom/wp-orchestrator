<?php

namespace App\Exceptions;

/**
 * Eccezione lanciata quando viene rilevato un dominio/WP già esistente.
 * Interrompe la pipeline di provisioning normale in modo controllato
 * senza marcare il sito come "error".
 */
class ImportDetectedException extends \RuntimeException
{
}
