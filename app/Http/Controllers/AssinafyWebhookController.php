<?php

namespace App\Http\Controllers;

use App\Models\Locacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsssinafyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Captura o evento (no seu JSON veio 'signer_signed_document')
        $event      = $request->input('event');   
        
        // 2. CORREÇÃO: Captura o ID do documento dentro do objeto (no seu JSON veio '10312985fbc7fa027130d6f5321e')
        $documentId = $request->input('object.id'); 

        Log::info('Assinafy webhook recebido', ['event' => $event, 'document_id' => $documentId]);

        // Busca no banco pela locação que possui este ID de documento cadastrado
        $locacao = Locacao::where('assinafy_document_id', $documentId)->first();

        if (!$locacao) {
            Log::warning('Webhook Assinafy recebido, mas nenhuma locacao foi encontrada com esse ID de documento.', ['document_id' => $documentId]);
            return response()->json(['ok' => true]); // ignora documentos não mapeados
        }

        // 3. CORREÇÃO: Mapeia o evento correto enviado no JSON
        $novoStatus = match ($event) {
            'signer_signed_document' => 'signed',
            'document.refused'       => 'refused', // Caso use recusa, valide se o evento deles segue o padrão do acima
            'document.viewed'        => 'viewed',
            default                  => $locacao->assinafy_status,
        };

        $locacao->update(['assinafy_status' => $novoStatus]);

        return response()->json(['ok' => true]);
    }
}