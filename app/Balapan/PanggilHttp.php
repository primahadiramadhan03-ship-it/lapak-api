<?php

declare(strict_types=1);

namespace App\Balapan;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait untuk memfasilitasi dispatch HTTP request internal
 * di dalam skenario proses anak balapan (Modul Hal 26).
 */
trait PanggilHttp
{
    /**
     * Mengirimkan permintaan HTTP internal langsung ke kernel aplikasi Laravel.
     *
     * @param  string  $metode  Metode HTTP (GET, POST, PUT, PATCH, DELETE)
     * @param  string  $uri  Alamat URI API (mis. '/api/v1/checkout')
     * @param  array<string, mixed>  $data  Payload atau parameter permintaan
     * @param  array<string, string>  $headers  Header tambahan (mis. Authorization, Idempotency-Key)
     */
    public function panggil(
        string $metode,
        string $uri,
        array $data = [],
        array $headers = [],
    ): Response {
        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);

        $server = [
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $kunci => $nilai) {
            $kunciNormal = strtoupper(str_replace('-', '_', $kunci));
            $server['HTTP_'.$kunciNormal] = $nilai;
        }

        $konten = ! empty($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $request = Request::create(
            uri: $uri,
            method: $metode,
            parameters: $data,
            server: $server,
            content: $konten,
        );

        $request->headers->set('Accept', 'application/json');
        if (! empty($data)) {
            $request->headers->set('Content-Type', 'application/json');
        }

        foreach ($headers as $kunci => $nilai) {
            $request->headers->set($kunci, $nilai);
        }

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }

    /**
     * Memanggil endpoint JSON dan mengembalikan data array terurai beserta kodenya.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array{status: int, data: array<string, mixed>, headers: array<string, mixed>}
     */
    public function panggilJson(
        string $metode,
        string $uri,
        array $data = [],
        array $headers = [],
    ): array {
        $response = $this->panggil($metode, $uri, $data, $headers);
        $isi = json_decode($response->getContent() ?: '', true) ?? [];

        return [
            'status' => $response->getStatusCode(),
            'data' => $isi,
            'headers' => $response->headers->all(),
        ];
    }
}
