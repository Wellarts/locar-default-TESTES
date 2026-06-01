<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssinafyService
{
    private string $baseUrl;
    private string $apiKey;
    private string $accountId;

    public function __construct()
    {
        $this->baseUrl   = config('services.assinafy.base_url');
        $this->apiKey    = config('services.assinafy.key');
        $this->accountId = config('services.assinafy.account_id');
    }

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    /**
     * Cria ou busca um signer pelo e-mail.
     * A API não permite duplicar e-mail, então trata o erro graciosamente.
     */
    private function obterOuCriarSigner(string $nome, string $email): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->baseUrl($this->baseUrl)->post("/accounts/{$this->accountId}/signers", [
            'full_name' => $nome,
            'email'     => $email,
        ]);

        Log::info('Assinafy criar signer', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        // Criado com sucesso
        if ($response->successful()) {
            return $response->json('data.id');
        }

        // Já existe — busca pelo e-mail
        if (str_contains($response->json('message', ''), 'já existe')) {
            $lista = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json',
            ])->baseUrl($this->baseUrl)->get("/accounts/{$this->accountId}/signers", [
                'email' => $email,
            ]);

            Log::info('Assinafy buscar signer existente', [
                'status' => $lista->status(),
                'body'   => $lista->json(),
            ]);

            $signerId = $lista->json('data.0.id') ?? $lista->json('data.id');

            if ($signerId) {
                return $signerId;
            }
        }

        throw new \RuntimeException('Não foi possível criar ou encontrar signer: ' . $response->body());
    }

    /**
     * Envia um PDF para assinatura eletrônica.
     */
    public function enviarDocumento(
        string $pdfContent,
        string $filename,
        array  $signatarios,
        string $titulo = 'Contrato de Locação',
        int    $diasExpiracao = 30
    ): array {
        // 1. Criar signers e coletar IDs
        $signers = [];
        foreach ($signatarios as $index => $sig) {
            $signerId = $this->obterOuCriarSigner($sig['name'], $sig['email']);
            $signers[] = [
                'id'                   => $signerId,
                'verification_method'  => $sig['verification_method']  ?? 'Email',
                'notification_methods' => $sig['notification_methods'] ?? ['Email'],
                'step'                 => $sig['step'] ?? ($index + 1),
            ];
        }

        // 2. Upload do PDF
        $uploadResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ])->baseUrl($this->baseUrl)
          ->attach('file', $pdfContent, $filename, ['Content-Type' => 'application/pdf'])
          ->post("/accounts/{$this->accountId}/documents");

        Log::info('Assinafy upload', [
            'status' => $uploadResponse->status(),
            'body'   => $uploadResponse->json(),
        ]);

        if ($uploadResponse->failed()) {
            throw new \RuntimeException('Falha no upload do documento: ' . $uploadResponse->body());
        }

        $documentId = $uploadResponse->json('data.id');

        if (!$documentId) {
            throw new \RuntimeException('Document ID não retornado pelo upload.');
        }

        // 3. Aguarda processamento do PDF (até metadata_ready)
        $tentativas = 0;
        do {
            sleep(2);
            $status = $this->http()->get("/documents/{$documentId}")->json('data.status');
            $tentativas++;
        } while ($status === 'uploaded' && $tentativas < 10);

        Log::info('Assinafy status após upload', ['status' => $status, 'tentativas' => $tentativas]);

        // 4. Criar assignment (dispara envio ao cliente)
        $assignResponse = $this->http()->post("/documents/{$documentId}/assignments", [
            'signers'    => $signers,
            'method'     => 'virtual',
            'expiration' => now()->addDays($diasExpiracao)->format('Y-m-d'),
        ]);

        Log::info('Assinafy assignment', [
            'status' => $assignResponse->status(),
            'body'   => $assignResponse->json(),
        ]);

        if ($assignResponse->failed()) {
            throw new \RuntimeException('Falha ao criar assignment: ' . $assignResponse->body());
        }

        $assignment = $assignResponse->json('data');

        return [
            'id'          => $documentId,
            'assignment'  => $assignment,
            'signing_url' => $assignment['signing_urls'][0]['url'] ?? null,
            'status'      => 'sent',
        ];
    }

    /**
     * Consulta status de um documento.
     */
    public function statusDocumento(string $documentId): array
    {
        $response = $this->http()->get("/documents/{$documentId}");
        return $response->json('data') ?? [];
    }

    /**
     * Baixa o PDF assinado.
     */
    public function downloadAssinado(string $documentId): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->baseUrl($this->baseUrl)->get("/documents/{$documentId}/download/original");
        return $response->body();
    }

    /**
     * Baixa o PDF com as assinaturas e certificado acoplados.
     */
    public function downloadFinalAssinado(string $documentId): string
    {
        // Alterado de /download/original para /download/signed
        // (Caso a API deles use outro padrão, pode ser apenas /download ou /download/completed)
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->baseUrl($this->baseUrl)->get("/documents/{$documentId}/download/signed");

        // Se o endpoint acima der erro 404, tente a rota padrão abaixo (comentada):
        // $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
        //     ->baseUrl($this->baseUrl)->get("/documents/{$documentId}/download");

        return $response->body();
    }

    
}