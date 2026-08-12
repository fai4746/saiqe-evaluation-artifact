<?php
/**
 * EvidenceStore — the shared evidence spine (framework principle P1).
 *
 * Every quality, security and compliance artifact is written once, hashed,
 * signed, and given the obligation references that make it usable as
 * conformity evidence rather than only as a build log.
 *
 * Append-only: records are written as JSON Lines and chained by hash, so a
 * record cannot be altered or removed without breaking every record after it.
 * Signing uses HMAC-SHA256 with a key held outside the store. That is weaker
 * than the Sigstore/in-toto attestation the framework calls for — see the
 * instrumentation note in the paper — but it gives the same two properties the
 * evaluation needs: integrity, and attribution to a producer.
 */
class EvidenceStore {

    private string $path;
    private string $key;
    private string $arm;

    public function __construct(string $path, string $arm, string $key) {
        $this->path = $path;
        $this->arm  = $arm;
        $this->key  = $key;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
    }

    /**
     * @param string $controlId  control from the catalogue this satisfies
     * @param array  $obligations regulatory references, e.g. ['EU AI Act Art. 15']
     * @param array  $payload    the artifact itself, or a reference plus digest
     */
    public function append(
        string $controlId,
        string $stage,
        string $decision,
        array  $obligations,
        array  $payload,
        string $retentionClass = 'standard'
    ): array {
        $previous = $this->headHash();

        $record = [
            'record_id'      => bin2hex(random_bytes(8)),
            'arm'            => $this->arm,
            'control_id'     => $controlId,
            'pipeline_stage' => $stage,
            'decision'       => $decision,
            'obligations'    => $obligations,
            'producer'       => 'saiqe-harness/1.0',
            'timestamp'      => date('c'),
            'retention_class'=> $retentionClass,
            'payload_digest' => hash('sha256', json_encode($payload)),
            'payload'        => $payload,
            'previous_hash'  => $previous,
        ];

        $record['record_hash'] = hash('sha256', json_encode($record));
        $record['signature']   = hash_hmac('sha256', $record['record_hash'], $this->key);

        file_put_contents($this->path, json_encode($record) . "\n", FILE_APPEND);
        return $record;
    }

    public function all(): array {
        if (!is_file($this->path)) return [];
        $out = [];
        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $out[] = json_decode($line, true);
        }
        return $out;
    }

    private function headHash(): ?string {
        $all = $this->all();
        return $all ? end($all)['record_hash'] : null;
    }

    /**
     * Verify every signature and the hash chain.
     * @return array ['intact'=>bool, 'records'=>int, 'signature_failures'=>int, 'chain_breaks'=>int]
     */
    public function verify(): array {
        $records = $this->all();
        $sigFail = 0; $chainBreak = 0; $previous = null;

        foreach ($records as $r) {
            $signature = $r['signature'];
            $recorded  = $r['record_hash'];

            $copy = $r;
            unset($copy['record_hash'], $copy['signature']);
            if (hash('sha256', json_encode($copy)) !== $recorded) $chainBreak++;
            if (!hash_equals(hash_hmac('sha256', $recorded, $this->key), $signature)) $sigFail++;
            if ($r['previous_hash'] !== $previous) $chainBreak++;

            $previous = $recorded;
        }

        return [
            'intact'             => $sigFail === 0 && $chainBreak === 0,
            'records'            => count($records),
            'signature_failures' => $sigFail,
            'chain_breaks'       => $chainBreak,
        ];
    }
}
