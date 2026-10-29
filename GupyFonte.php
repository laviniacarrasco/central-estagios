<?php
/**
 * GupyFonte.php — Central de Estágios FSA
 * Busca vagas de estágio/trainee na busca pública da Gupy (sem chave, sem cartão).
 *
 * ATENÇÃO: endpoint não oficial (é o que o portal da Gupy usa). Pode mudar sem aviso.
 * Por isso: poucas chamadas, intervalo entre elas, e sempre link para a vaga original.
 */

class GupyFonte
{
    // Tenta na ordem; usa o primeiro que responder com JSON válido.
    private const ENDPOINTS = [
        'https://employability-portal.gupy.io/api/v1/jobs',
        'https://portal.api.gupy.io/api/v1/jobs',
    ];

    private const TIPOS_ACEITOS = [
        'vacancy_type_internship',   // estágio
        'vacancy_type_trainee',      // trainee
        'vacancy_type_apprentice',   // jovem aprendiz (remova se não quiser)
    ];

    private array $termos;
    private array $cidades;
    private int $limitePorTermo;
    private int $pausaMs;
    private array $log = [];

    public function __construct(
        array $termos = ['estágio', 'estagio', 'trainee'],
        array $cidades = ['São Paulo', 'Santo André', 'São Bernardo do Campo',
                          'São Caetano do Sul', 'Diadema', 'Mauá'],
        int $limitePorTermo = 100,
        int $pausaMs = 1500
    ) {
        $this->termos = $termos;
        $this->cidades = array_map([$this, 'normalizar'], $cidades);
        $this->limitePorTermo = $limitePorTermo;
        $this->pausaMs = $pausaMs;
    }

    /** Retorna lista de vagas já normalizadas no formato da Central. */
    public function buscar(): array
    {
        $vagas = [];
        foreach ($this->termos as $i => $termo) {
            if ($i > 0) usleep($this->pausaMs * 1000);

            $dados = $this->chamar($termo);
            if ($dados === null) continue;

            foreach ($dados as $j) {
                $v = $this->converter($j);
                if ($v !== null) $vagas[$v['id_externo']] = $v; // remove duplicadas
            }
        }
        $this->log[] = 'Vagas aproveitadas: ' . count($vagas);
        return array_values($vagas);
    }

    public function log(): array { return $this->log; }

    // ---------------------------------------------------------------

    private function chamar(string $termo): ?array
    {
        $qs = http_build_query([
            'jobName' => $termo,
            'limit'   => $this->limitePorTermo,
            'offset'  => 0,
        ]);

        foreach (self::ENDPOINTS as $base) {
            $ch = curl_init("$base?$qs");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'User-Agent: CentralEstagiosFSA/1.0 (+https://central-estagios-fsa.onrender.com)',
                ],
            ]);
            $corpo  = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro   = curl_error($ch);
            curl_close($ch);

            if ($corpo === false || $status !== 200) {
                $this->log[] = "[$termo] $base -> HTTP $status $erro";
                continue;
            }
            $json = json_decode($corpo, true);
            $lista = $json['data'] ?? null;
            if (!is_array($lista)) {
                $this->log[] = "[$termo] $base -> formato inesperado";
                continue;
            }
            $this->log[] = "[$termo] $base -> " . count($lista) . ' vagas brutas';
            return $lista;
        }
        $this->log[] = "[$termo] nenhuma URL da Gupy respondeu (a API pode ter mudado)";
        return null;
    }

    private function converter(array $j): ?array
    {
        $tipo = $j['type'] ?? '';
        if (!in_array($tipo, self::TIPOS_ACEITOS, true)) return null;

        $cidade = $j['city'] ?? '';
        $remoto = !empty($j['isRemoteWork']) || ($j['workplaceType'] ?? '') === 'remote';

        // Aceita: cidades da lista OU vagas remotas
        if (!$remoto && !in_array($this->normalizar($cidade), $this->cidades, true)) return null;

        // Ignora vagas com prazo vencido
        $prazo = $j['applicationDeadline'] ?? null;
        if ($prazo && strtotime($prazo) < time()) return null;

        return [
            'id_externo'  => 'gupy_' . ($j['id'] ?? md5($j['jobUrl'] ?? '')),
            'fonte'       => 'gupy',
            'titulo'      => trim($j['name'] ?? ''),
            'empresa'     => trim($j['careerPageName'] ?? ''),
            'cidade'      => $cidade,
            'estado'      => $j['state'] ?? '',
            'remoto'      => $remoto,
            'tipo'        => $tipo === 'vacancy_type_trainee' ? 'trainee' : 'estagio',
            'descricao'   => mb_substr(strip_tags($j['description'] ?? ''), 0, 2000),
            'link'        => $j['jobUrl'] ?? '',
            'publicada'   => $j['publishedDate'] ?? null,
            'prazo'       => $prazo,
            'status'      => 'aguardando_aprovacao', // continua passando pelo Admin Vagas
        ];
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e',
                        'í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        return $s;
    }
}
