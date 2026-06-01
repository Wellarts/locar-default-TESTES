<?php

namespace App\Filament\Resources;

use App\Filament\Exports\LocacaoExporter;
use App\Filament\Resources\LocacaoResource\Pages;
use App\Filament\Resources\LocacaoResource\RelationManagers\OcorrenciaRelationManager;
use App\Models\Cliente;
use App\Models\ContasReceber;
use App\Models\Estado;
use App\Models\FluxoCaixa;
use App\Models\Locacao;
use App\Models\Veiculo;
use Carbon\Carbon;
use DateTime;
use FFI;
use Filament\Tables\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Laravel\SerializableClosure\Serializers\Native;
use Leandrocfe\FilamentPtbrFormFields\Money;
use Illuminate\Support\Str;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LocacaoResource extends Resource
{
    protected static ?string $model = Locacao::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Locações';

    protected static ?string $navigationGroup = 'Locar';

    // Eager loading para evitar consultas N+1
    protected static function getEagerLoadRelations(): array
    {
        return ['cliente:id,nome,validade_cnh', 'veiculo:id,modelo,placa,km_atual,valor_diaria,valor_semana'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(null)
            ->schema([
                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Informações da Locação')
                            ->schema([
                                Fieldset::make('Dados da Locação')
                                    ->schema([
                                        Grid::make([
                                            'xl' => 4,
                                            '2xl' => 4,
                                        ])
                                            ->schema([
                                                Forms\Components\Select::make('cliente_id')
                                                    ->label('Cliente')
                                                    ->searchable()
                                                    ->autofocus(true)
                                                    ->extraInputAttributes(['tabindex' => 1])
                                                    ->native(false)
                                                    ->columnSpan([
                                                        'xl' => 2,
                                                        '2xl' => 2,
                                                    ])
                                                    ->live()
                                                    ->required(false)
                                                    ->relationship('cliente', 'nome')
                                                    ->createOptionForm([
                                                        Grid::make([
                                                            'xl' => 3,
                                                            '2xl' => 3,
                                                        ])
                                                            ->schema([
                                                                Forms\Components\TextInput::make('nome')
                                                                    ->label('Nome')
                                                                    ->columnSpan([
                                                                        'xl' => 2,
                                                                        '2xl' => 2,
                                                                    ])
                                                                    ->maxLength(255),
                                                                Forms\Components\TextInput::make('cpf_cnpj')
                                                                    ->label('CPF/CNPJ')
                                                                    ->mask(RawJs::make(<<<'JS'
                                                                $input.length > 14 ? '99.999.999/9999-99' : '999.999.999-99'
                                                            JS))
                                                                    ->rule('cpf_ou_cnpj'),
                                                                Forms\Components\Textarea::make('endereco')
                                                                    ->label('Endereço')
                                                                    ->columnSpanFull(),
                                                                Forms\Components\Select::make('estado_id')
                                                                    ->label('Estado')
                                                                    ->native(false)
                                                                    ->searchable()
                                                                    ->required()
                                                                    ->default(33)
                                                                    ->options(function () {
                                                                        return Estado::all()->pluck('nome', 'id')->toArray();
                                                                    })
                                                                    ->live(),
                                                                Forms\Components\Select::make('cidade_id')
                                                                    ->label('Cidade')
                                                                    ->default(3243)
                                                                    ->native(false)
                                                                    ->searchable()
                                                                    ->required()
                                                                    ->options(function (callable $get) {
                                                                        $estadoId = $get('estado_id');
                                                                        if (!$estadoId) {
                                                                            return [];
                                                                        }
                                                                        $estado = Estado::with('cidade:id,nome,estado_id')->find($estadoId);
                                                                        return $estado ? $estado->cidade->pluck('nome', 'id')->toArray() : [];
                                                                    })
                                                                    ->live(),
                                                                Forms\Components\TextInput::make('telefone_1')
                                                                    ->label('Telefone 1')
                                                                    ->tel()
                                                                    ->mask('(99)99999-9999'),
                                                                Forms\Components\TextInput::make('telefone_2')
                                                                    ->tel()
                                                                    ->label('Telefone 2')
                                                                    ->tel()
                                                                    ->mask('(99)99999-9999'),
                                                                Forms\Components\TextInput::make('email')
                                                                    ->columnSpan([
                                                                        'xl' => 2,
                                                                        '2xl' => 2,
                                                                    ])
                                                                    ->email()
                                                                    ->maxLength(255),
                                                                Forms\Components\TextInput::make('rede_social')
                                                                    ->label('Rede Social'),
                                                                Forms\Components\TextInput::make('cnh')
                                                                    ->label('CNH'),
                                                                Forms\Components\DatePicker::make('validade_cnh')
                                                                    ->label('Valiade da CNH'),
                                                                Forms\Components\TextInput::make('rg')
                                                                    ->label('RG'),
                                                                Forms\Components\TextInput::make('exp_rg')
                                                                    ->label('Orgão Exp.'),
                                                                Forms\Components\Select::make('estado_exp_rg')
                                                                    ->searchable()
                                                                    ->label('UF - Expedidor')
                                                                    ->options(function () {
                                                                        return Estado::all()->pluck('nome', 'id')->toArray();
                                                                    }),
                                                                FileUpload::make('img_cnh')
                                                                    ->columnSpan([
                                                                        'xl' => 2,
                                                                        '2xl' => 2,
                                                                    ])
                                                                    ->downloadable()
                                                                    ->label('Foto CNH'),
                                                                Forms\Components\DatePicker::make('data_nascimento')
                                                                    ->label('Data de Nascimento'),
                                                            ])
                                                    ])
                                                    ->afterStateUpdated(function ($state) {
                                                        if ($state != null) {
                                                            $cliente = Cliente::select('validade_cnh')->find($state);
                                                            if ($cliente && $cliente->validade_cnh) {
                                                                Notification::make()
                                                                    ->title('ATENÇÃO')
                                                                    ->body('A validade da CNH do cliente selecionado: ' . Carbon::parse($cliente->validade_cnh)->format('d/m/Y'))
                                                                    ->warning()
                                                                    ->persistent()
                                                                    ->send();
                                                            }
                                                        }
                                                    }),
                                                Forms\Components\Select::make('veiculo_id')
                                                    ->required(false)
                                                    ->label('Veículo')
                                                    ->live(onBlur: true)
                                                    ->options(function ($context) {
                                                        $query = Veiculo::where('status', 1);

                                                        if ($context === 'create') {
                                                            $query->where('status_locado', 0);
                                                        }

                                                        return $query->orderBy('modelo')
                                                            ->orderBy('placa')
                                                            ->get()
                                                            ->mapWithKeys(fn($record) => [
                                                                $record->id => "{$record->modelo} {$record->placa}"
                                                            ])->toArray();
                                                    })
                                                    ->searchable()
                                                    ->afterStateUpdated(function (Set $set, $state) {
                                                        if ($state) {
                                                            $veiculo = Veiculo::select('km_atual')->find($state);
                                                            $set('km_saida', $veiculo->km_atual);
                                                        }
                                                    })
                                                    ->columnSpan([
                                                        'xl' => 2,
                                                        '2xl' => 2,
                                                    ]),
                                                Forms\Components\Radio::make('forma_locacao')
                                                    ->label('Forma de Locação')
                                                    ->options([
                                                        '1' => 'Diária',
                                                        '2' => 'Semanal',
                                                    ])
                                                    ->inline()
                                                    ->inlineLabel(false)
                                                    ->live()
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        if ($state == 1) {
                                                            $set('qtd_semanas', null);
                                                        } else {
                                                            $set('qtd_diarias', null);
                                                        }
                                                        self::recalcularValores($get, $set);
                                                    })
                                                    ->default(1)
                                                    ->required()
                                                    ->columnSpan([
                                                        'xl' => 2,
                                                        '2xl' => 2,
                                                    ]),
                                                Forms\Components\TextInput::make('qtd_diarias')
                                                    ->extraInputAttributes(['style' => 'font-weight: bolder; font-size: 1rem; color: #CF9A16;'])
                                                    ->label('Qtd Dias')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                        if (!$state || !$get('veiculo_id') || !$get('data_saida') || !$get('hora_saida')) {
                                                            return;
                                                        }

                                                        $dataSaida   = Carbon::parse($get('data_saida'));
                                                        $dataRetorno = $dataSaida->copy()->addDays($state);

                                                        $veiculo = Veiculo::select('valor_diaria')->find($get('veiculo_id'));
                                                        if (!$veiculo) return;

                                                        $valorTotal = $veiculo->valor_diaria * $state;

                                                        $set('data_retorno', $dataRetorno->format('Y-m-d'));
                                                        $set('hora_retorno', Carbon::parse($get('hora_saida'))->format('H:i'));
                                                        $set('valor_total', $valorTotal);
                                                        $set('valor_desconto', 0);
                                                        $set('valor_total_desconto', $valorTotal);
                                                    })
                                                    ->hidden(fn(Get $get) => $get('forma_locacao') == 2)
                                                    ->required(fn(Get $get) => $get('forma_locacao') == 1),
                                                Forms\Components\TextInput::make('qtd_semanas')
                                                    ->extraInputAttributes(['style' => 'font-weight: bolder; font-size: 1rem; color: #CF9A16;'])
                                                    ->label('Qtd Semanas')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                        if (!$state || !$get('veiculo_id') || !$get('data_saida') || !$get('hora_saida')) {
                                                            return;
                                                        }

                                                        $dataSaida   = Carbon::parse($get('data_saida'));
                                                        $dataRetorno = $dataSaida->copy()->addWeeks($state);
                                                        $qtdDias     = $dataSaida->diffInDays($dataRetorno);

                                                        $veiculo = Veiculo::select('valor_semana')->find($get('veiculo_id'));
                                                        if (!$veiculo) return;

                                                        $valorTotal = $veiculo->valor_semana * $state;

                                                        $set('data_retorno', $dataRetorno->format('Y-m-d'));
                                                        $set('hora_retorno', Carbon::parse($get('hora_saida'))->format('H:i'));
                                                        $set('qtd_diarias', $qtdDias);
                                                        $set('valor_total', $valorTotal);
                                                        $set('valor_desconto', 0);
                                                        $set('valor_total_desconto', $valorTotal);
                                                    })
                                                    ->hidden(fn(Get $get) => $get('forma_locacao') == 1)
                                                    ->required(fn(Get $get) => $get('forma_locacao') == 2),
                                            ]),
                                    ]),
                                Fieldset::make('Datas e Valores')
                                    ->schema([
                                        Grid::make([
                                            'xl' => 4,
                                            '2xl' => 4,
                                        ])
                                            ->schema([
                                                Forms\Components\DatePicker::make('data_saida')
                                                    ->default(Carbon::today())
                                                    ->displayFormat('d/m/Y')
                                                    ->label('Data Saída')
                                                    ->required(false)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                                        self::recalcularValores($get, $set);
                                                    }),
                                                Forms\Components\TimePicker::make('hora_saida')
                                                    ->seconds(false)
                                                    ->default(Carbon::now())
                                                    ->label('Hora Saída')
                                                    ->required(false),
                                                Forms\Components\DatePicker::make('data_retorno')
                                                    ->displayFormat('d/m/Y')
                                                    ->label('Data Retorno')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                                        self::recalcularValores($get, $set);
                                                    })
                                                    ->required(false),
                                                Forms\Components\TimePicker::make('hora_retorno')
                                                    ->seconds(false)
                                                    ->default(Carbon::now())
                                                    ->label('Hora Retorno')
                                                    ->required(false),
                                                Forms\Components\TextInput::make('km_saida')
                                                    ->label('Km Saída')
                                                    ->required(false),
                                                Forms\Components\TextInput::make('km_retorno')
                                                    ->hidden(fn($context) => $context === 'create')
                                                    ->label('Km Retorno'),
                                                Forms\Components\TextInput::make('valor_caucao')
                                                    ->label('Valor Caução')
                                                    ->hint('Caso exista, registre o valor do caução')
                                                    ->columnSpan([
                                                        'xl' => 2,
                                                        '2xl' => 2,
                                                    ])
                                                    ->numeric()
                                                    ->prefix('R$')
                                                    ->inputMode('decimal')
                                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2),
                                            ]),
                                        Forms\Components\TextInput::make('valor_total')
                                            ->extraInputAttributes(['style' => 'font-weight: bolder; font-size: 1rem; color: #D33644;'])
                                            ->label('Valor Total')
                                            ->prefix('R$')
                                            ->inputMode('decimal')
                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                            ->readOnly()
                                            ->required(false),
                                        Forms\Components\TextInput::make('valor_desconto')
                                            ->extraInputAttributes(['style' => 'font-weight: bolder; font-size: 1rem; color: #3668D3;'])
                                            ->label('Desconto')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('R$')
                                            ->inputMode('decimal')
                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                            ->required(true)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $valorTotal = (float) ($get('valor_total') ?? 0);
                                                $desconto   = (float) $state;
                                                $set('valor_total_desconto', $valorTotal - $desconto);
                                            }),
                                        Forms\Components\TextInput::make('valor_total_desconto')
                                            ->extraInputAttributes(['style' => 'font-weight: bolder; font-size: 1rem; color: #17863E;'])
                                            ->label('Valor Total com Desconto')
                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                            ->numeric()
                                            ->prefix('R$')
                                            ->inputMode('decimal')
                                            ->readOnly()
                                            ->required(false),
                                        Forms\Components\Textarea::make('obs')
                                            ->autosize()
                                            ->columnSpanFull()
                                            ->label('Observações'),
                                    ]),
                                Fieldset::make('Financeiro')
                                    ->schema([
                                        Grid::make([
                                            'xl' => 4,
                                            '2xl' => 4,
                                        ])
                                            ->schema([
                                                Forms\Components\Toggle::make('status_financeiro')
                                                    ->live()
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->afterStateUpdated(
                                                        function (Get $get, Set $set, $state) {
                                                            if ($state == true) {
                                                                $set('valor_total_financeiro', ((float)($get('valor_total_desconto') ?? 0)));
                                                            } else {
                                                                $set('valor_parcela_financeiro', 0);
                                                                $set('parcelas_financeiro', null);
                                                                $set('formaPgmto_financeiro', null);
                                                                $set('valor_total_financeiro', 0);
                                                            }
                                                        }
                                                    )
                                                    ->columnSpan([
                                                        'xl' => 1,
                                                        '2xl' => 1,
                                                    ])
                                                    ->label('Desejar lançar no financeiro?'),
                                                Forms\Components\Toggle::make('status_pago_financeiro')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->live()
                                                    ->afterStateUpdated(
                                                        function (Get $get, Set $set, $state) {
                                                            if ($state == true) {
                                                                $set('parcelas_financeiro', 1);
                                                                $set('valor_parcela_financeiro', ((float)($get('valor_total_desconto') ?? 0)));
                                                                $set('data_vencimento_financeiro', Carbon::now()->format('Y-m-d'));
                                                            } else {
                                                                $set('valor_parcela_financeiro', null);
                                                                $set('parcelas_financeiro', null);
                                                                $set('formaPgmto_financeiro', null);
                                                            }
                                                        }
                                                    )
                                                    ->label('Recebido'),
                                                Forms\Components\Select::make('proxima_parcela')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->options([
                                                        '7'  => 'Semanal',
                                                        '15' => 'Quinzenal',
                                                        '30' => 'Mensal',
                                                    ])
                                                    ->default(7)
                                                    ->label('Próximas Parcelas'),
                                                Forms\Components\TextInput::make('parcelas_financeiro')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(
                                                        function (Get $get, Set $set) {
                                                            $valorTotal = (float) ($get('valor_total_financeiro') ?? 0);
                                                            $parcelas   = (int) ($get('parcelas_financeiro') ?? 1);
                                                            if ($parcelas > 0) {
                                                                $set('valor_parcela_financeiro', $valorTotal / $parcelas);
                                                                $set('data_vencimento_financeiro', Carbon::now()->addDays($get('proxima_parcela'))->format('Y-m-d'));
                                                            }
                                                        }
                                                    )
                                                    ->numeric()
                                                    ->label('Qtd Parcelas')
                                                    ->required(fn(Get $get): bool => $get('status_financeiro')),
                                                Forms\Components\Select::make('forma_pgmto_id')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->default(1)
                                                    ->label('Forma de Pagamento')
                                                    ->required(fn(Get $get): bool => $get('status_financeiro'))
                                                    ->relationship('formaPgmto', 'nome'),
                                                Forms\Components\DatePicker::make('data_vencimento_financeiro')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->required(fn(Get $get): bool => $get('status_financeiro'))
                                                    ->displayFormat('d/m/Y')
                                                    ->label("Vencimento da 1º"),
                                                Forms\Components\TextInput::make('valor_parcela_financeiro')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                                    ->numeric()
                                                    ->prefix('R$')
                                                    ->inputMode('decimal')
                                                    ->label('Valor da Parcela')
                                                    ->readOnly()
                                                    ->required(false),
                                                Forms\Components\TextInput::make('valor_total_financeiro')
                                                    ->hidden(fn(Get $get): bool => !$get('status_financeiro'))
                                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                                    ->numeric()
                                                    ->prefix('R$')
                                                    ->inputMode('decimal')
                                                    ->label('Valor Total')
                                                    ->readOnly()
                                                    ->required(false),
                                            ]),
                                    ]),
                                Fieldset::make('Controle da Locação')
                                    ->schema([
                                        ToggleButtons::make('status')
                                            ->options([
                                                '0' => 'Locado',
                                                '1' => 'Finalizar',
                                            ])
                                            ->colors([
                                                '0' => 'danger',
                                                '1' => 'success',
                                            ])
                                            ->inline()
                                            ->default(0)
                                            ->label(''),
                                    ])
                            ]),
                        Forms\Components\Tabs\Tab::make('Assinaturas de Terceiros')
                            ->schema([
                                Fieldset::make('Assinaturas no Contrato')
                                    ->schema([
                                        Forms\Components\TextInput::make('testemunha_1')
                                            ->label('Testemunha 1')
                                            ->required(false),
                                        Forms\Components\TextInput::make('testemunha_1_rg')
                                            ->label('RG'),
                                        Forms\Components\TextInput::make('testemunha_2')
                                            ->label('Testemunha 2')
                                            ->required(false),
                                        Forms\Components\TextInput::make('testemunha_2_rg')
                                            ->label('RG'),
                                        Forms\Components\TextInput::make('fiador')
                                            ->label('Fiador')
                                            ->required(false),
                                    ]),
                                Fieldset::make('Dados Completo do Fiador')
                                    ->schema([
                                        Forms\Components\Textarea::make('dados_fiador')
                                            ->label('')
                                            ->autosize()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Forms\Components\Tabs\Tab::make('Ocorrências')
                            ->schema([
                                Fieldset::make('Ocorrências da Locação')
                                    ->schema([
                                        Repeater::make('ocorrencia')
                                            ->label('Ocorrências')
                                            ->relationship('ocorrencias')
                                            ->schema([
                                                Grid::make([
                                                    'xl' => 3,
                                                    '2xl' => 3,
                                                ])
                                                    ->schema([
                                                        Select::make('tipo')
                                                            ->options([
                                                                'multa'           => 'Multa',
                                                                'colisao'         => 'Colisão',
                                                                'avaria'          => 'Avaria',
                                                                'danos_terceiros' => 'Danos a Terceiros',
                                                                'outro'           => 'Outros',
                                                            ]),
                                                        DateTimePicker::make('data_hora'),
                                                        TextInput::make('valor')
                                                            ->numeric(),
                                                        Textarea::make('descricao')
                                                            ->columnSpan(2)
                                                            ->autosize()
                                                            ->label('Descrição'),
                                                        ToggleButtons::make('status')
                                                            ->label('Concluído?')
                                                            ->default(false)
                                                            ->boolean()
                                                            ->grouped()
                                                    ])
                                            ])
                                            ->columnSpanFull()
                                            ->addActionLabel('Novo')
                                            ->defaultItems(0)
                                    ]),
                            ]),
                    ])
                    ->activeTab(1)
                    ->persistTabInQueryString()
            ]);
    }

    /**
     * Recalcular valores da locação
     */
    private static function recalcularValores(Get $get, Set $set): void
    {
        $veiculoId    = $get('veiculo_id');
        $dataSaida    = $get('data_saida');
        $dataRetorno  = $get('data_retorno');
        $formaLocacao = $get('forma_locacao');

        if (!$veiculoId || !$dataSaida || !$dataRetorno) {
            return;
        }

        $veiculo = Veiculo::select('valor_diaria', 'valor_semana')->find($veiculoId);
        if (!$veiculo) return;

        $dtSaida   = Carbon::parse($dataSaida);
        $dtRetorno = Carbon::parse($dataRetorno);
        $qtdDias   = $dtRetorno->diffInDays($dtSaida);

        if ($formaLocacao == 1) {
            $set('qtd_diarias', $qtdDias);
            $valorTotal = $veiculo->valor_diaria * $qtdDias;
        } else {
            $qtdSemanas = ceil($qtdDias / 7);
            $set('qtd_semanas', $qtdSemanas);
            $dataRetornoAjustada = $dtSaida->copy()->addWeeks($qtdSemanas);
            $set('data_retorno', $dataRetornoAjustada->format('Y-m-d'));
            $valorTotal = $veiculo->valor_semana * $qtdSemanas;
        }

        $set('valor_total', $valorTotal);
        $set('valor_total_desconto', $valorTotal - ((float) ($get('valor_desconto') ?? 0)));

        $diferencaEmDias    = $dtSaida->diffInDays($dtRetorno);
        $diferencaEmSemanas = $diferencaEmDias / 7;
        $semanasCompletas   = floor($diferencaEmSemanas);
        $diasRestantes      = $diferencaEmDias % 7;
        $mesesCompleto      = floor($diferencaEmDias / 30);
        $diasRestantesMeses = $diferencaEmDias % 30;

        Notification::make()
            ->title('ATENÇÃO')
            ->body(
                'Para as datas escolhida temos:<br>
            <b>' . $diferencaEmDias . ' DIA(AS).</b><br>
            <b>' . $semanasCompletas . ' SEMANA(AS) e ' . $diasRestantes . ' DIA(AS). </b> <br>
            <b>' . $mesesCompleto . ' MÊS/MESES  e ' . $diasRestantesMeses . ' DIA(AS).</b><br>',
            )
            ->danger()
            ->persistent()
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable()
                    ->label('ID')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ocorrencias_pendentes_count')
                    ->label('Ocorrências Pendentes')
                    ->alignCenter()
                    ->color(fn($state): string => $state > 0 ? 'danger' : 'success')
                    ->icon(fn($state): ?string => $state > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->toggleable()
                    ->getStateUsing(function (Locacao $record): int {
                        return $record->ocorrencias()->where('status', false)->count();
                    }),
                Tables\Columns\TextColumn::make('cliente.nome')
                    ->sortable()
                    ->searchable()
                    ->label('Cliente')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('veiculo.modelo')
                    ->sortable()
                    ->searchable()
                    ->label('Veículo')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('veiculo.placa')
                    ->searchable()
                    ->label('Placa')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('data_saida')
                    ->label('Data Saída')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hora_saida')
                    ->alignCenter()
                    ->date('H:i')
                    ->sortable()
                    ->label('Hora Saída')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('data_retorno')
                    ->badge()
                    ->label('Data Retorno')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(static function ($state): string {
                        if (!$state) return 'secondary';

                        try {
                            $hoje        = Carbon::today();
                            $dataRetorno = Carbon::parse($state);
                            $qtdDias     = $hoje->diffInDays($dataRetorno, false);

                            if ($qtdDias <= 3 && $qtdDias >= 0) {
                                return 'warning';
                            }

                            if ($qtdDias < 0) {
                                return 'danger';
                            }

                            return 'success';
                        } catch (\Exception $e) {
                            return 'secondary';
                        }
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hora_retorno')
                    ->alignCenter()
                    ->date('H:i')
                    ->label('Hora Retorno')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('km_percorrido')
                    ->label('Km Total')
                    ->getStateUsing(function (Locacao $record): int {
                        return ($record->km_retorno - $record->km_saida);
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valor_total_desconto')
                    ->summarize(Sum::make()->money('BRL')->label('Total'))
                    ->money('BRL')
                    ->label('Valor Total')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->summarize(Count::make()->label('Total'))
                    ->Label('Status')
                    ->badge()
                    ->alignCenter()
                    ->color(fn(string $state): string => match ($state) {
                        '0'     => 'danger',
                        '1'     => 'success',
                        default => 'secondary'
                    })
                    ->formatStateUsing(fn($state) => $state == 0 ? 'Locado' : 'Finalizada')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('assinafy_status')
                    ->label('Assinatura')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'sent',
                        'success' => fn($state) => in_array($state, ['signed', 'certificated']),
                        'danger'  => 'refused',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sent'         => 'Aguardando',
                        'signed'       => 'Assinado',
                        'certificated' => 'Certificado',
                        'refused'      => 'Recusado',
                        default        => '—',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('Locados')
                    ->query(fn(Builder $query): Builder => $query->where('status', false))
                    ->default(1),
                SelectFilter::make('cliente')
                    ->searchable()
                    ->relationship('cliente', 'nome')
                    ->preload()
                    ->multiple(),
                SelectFilter::make('veiculo')
                    ->searchable()
                    ->relationship('veiculo', 'placa')
                    ->preload()
                    ->multiple(),
                Tables\Filters\Filter::make('datas')
                    ->form([
                        DatePicker::make('data_saida_de')
                            ->label('Saída de:'),
                        DatePicker::make('data_saida_ate')
                            ->label('Saída até:'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['data_saida_de'] ?? null,
                                fn($query) => $query->whereDate('data_saida', '>=', $data['data_saida_de'])
                            )
                            ->when(
                                $data['data_saida_ate'] ?? null,
                                fn($query) => $query->whereDate('data_saida', '<=', $data['data_saida_ate'])
                            );
                    })
            ])
            ->headerActions([
                Tables\Actions\Action::make('relatorio')
                    ->label('Relatório - PDF')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->modalHeading('Filtrar Relatório de Locações')
                    ->form([
                        \Filament\Forms\Components\Fieldset::make('Filtros')
                            ->columns([
                                'sm' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                \Filament\Forms\Components\Select::make('cliente_id')
                                    ->label('Cliente')
                                    ->relationship('cliente', 'nome')
                                    ->searchable()
                                    ->preload(),
                                \Filament\Forms\Components\Select::make('veiculo_id')
                                    ->label('Veículo')
                                    ->live(onBlur: true)
                                    ->relationship(
                                        name: 'veiculo',
                                        modifyQueryUsing: function (Builder $query, $context) {
                                            $query->where('status', 1)->orderBy('modelo')->orderBy('placa');
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->modelo} {$record->placa}")
                                    ->searchable(['modelo', 'placa']),
                                \Filament\Forms\Components\Select::make('forma_pgmto_id')
                                    ->label('Forma de Pagamento')
                                    ->relationship('formaPgmto', 'nome')
                                    ->searchable()
                                    ->preload(),
                                \Filament\Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        '0' => 'Locação Aberta',
                                        '1' => 'Locação Finalizada',
                                    ])
                                    ->searchable(),
                                \Filament\Forms\Components\DatePicker::make('data_saida')
                                    ->label('Data de Saída'),
                                \Filament\Forms\Components\DatePicker::make('data_retorno')
                                    ->label('Data de Retorno'),
                            ]),
                    ])
                    ->action(function (array $data, $livewire) {
                        $filteredData = [];
                        foreach ($data as $key => $value) {
                            if ($value !== '' && $value !== null) {
                                $filteredData[$key] = $value;
                            }
                        }

                        $query = http_build_query($filteredData);
                        $url   = route('imprimirLocacoesRelatorio') . ($query ? ('?' . $query) : '');
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
                ExportAction::make()
                    ->exporter(LocacaoExporter::class)
                    ->formats([
                        ExportFormat::Xlsx,
                    ])
                    ->columnMapping(false)
                    ->label('Exportar')
                    ->modalHeading('Confirmar exportação?'),
            ])
            ->actions([
                // Enviar para assinatura (visível apenas se ainda não enviado/assinado)
                Tables\Actions\Action::make('EnviarAssinatura')
                    ->label('Enviar p/ Assinatura')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(
                        fn(?Locacao $record) => $record !== null
                            && $record->Cliente?->email !== null
                            && !in_array($record->assinafy_status, ['sent', 'signed', 'certificated'])
                    )
                    ->form([
                        Forms\Components\Select::make('contrato_id')
                            ->label('Modelo de Documento')
                            ->options(fn() => \App\Models\Contrato::orderBy('titulo')->pluck('titulo', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('email_cliente')
                            ->label('E-mail do Cliente')
                            ->email()
                            ->default(fn(?Locacao $record) => $record?->Cliente?->email)
                            ->required(),
                        Forms\Components\Repeater::make('signatarios_extras')
                            ->label('Signatários Adicionais (opcional)')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Nome')->required(),
                                Forms\Components\TextInput::make('email')->label('E-mail')->email()->required(),
                            ])
                            ->defaultItems(0)
                            ->collapsible(),
                    ])
                    ->action(function (array $data, Locacao $record) {
                        try {
                            $assinafy = app(\App\Services\AssinafyService::class);

                            $signatarios = [
                                [
                                    'name'   => $record->Cliente->nome,
                                    'email'  => $data['email_cliente'],
                                    'action' => 'sign',
                                ],
                            ];

                            foreach ($data['signatarios_extras'] ?? [] as $extra) {
                                $signatarios[] = $extra;
                            }

                            $pdfContent = app(\App\Http\Controllers\Contrato::class)
                                ->gerarPdfContent($record->id, $data['contrato_id']);

                            $resultado = $assinafy->enviarDocumento(
                                pdfContent: $pdfContent,
                                filename: "contrato_locacao_{$record->id}.pdf",
                                signatarios: $signatarios,
                                titulo: "Contrato de Locação #{$record->id}"
                            );

                            $record->update([
                                'assinafy_document_id' => $resultado['id'] ?? null,
                                'assinafy_status'      => 'sent',
                            ]);

                            Notification::make()
                                ->title('Contrato enviado para assinatura!')
                                ->body('O cliente receberá o link por e-mail.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Log::error('Erro ao enviar para Assinafy', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Erro ao enviar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Enviar Contrato para Assinatura Eletrônica')
                    ->modalDescription('O cliente receberá um e-mail com o link para assinar digitalmente.')
                    ->modalSubmitActionLabel('Enviar'),

                // Baixar documento assinado (visível quando certificado)
                Tables\Actions\Action::make('BaixarDocumentoAssinado')
                    ->label('Baixar Doc. Assinado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(
                        fn(?Locacao $record) => $record !== null
                            && in_array($record->assinafy_status, ['signed', 'certificated'])
                            && !empty($record->assinafy_document_id)
                    )
                    ->action(function (Locacao $record, $livewire) {
                        $url = route('downloadAssinadoLocacao', ['locacao' => $record->id]);
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),

                // Gerar contrato PDF (visualização)
                Tables\Actions\Action::make('GerarContrato')
                    ->label('Gerar Documento')
                    ->icon('heroicon-o-document-text')
                    ->form([
                        Forms\Components\Select::make('contrato_id')
                            ->label('Escolha o Modelo de Documento')
                            ->options(function () {
                                return \App\Models\Contrato::orderBy('titulo')->pluck('titulo', 'id')->toArray();
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, Locacao $record, $livewire) {
                        $url = route('imprimirLocacaoContrato', ['locacao' => $record->id, 'contrato' => $data['contrato_id']]);
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),

                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar locação')
                    ->after(function ($data) {
                        if (isset($data['status']) && $data['status'] == 1 && isset($data['veiculo_id'])) {
                            DB::table('veiculos')
                                ->where('id', $data['veiculo_id'])
                                ->update([
                                    'km_atual'      => $data['km_retorno'],
                                    'status_locado' => 0,
                                ]);
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->veiculo_id) {
                            DB::table('veiculos')
                                ->where('id', $record->veiculo_id)
                                ->update(['status_locado' => 0]);
                        }
                        if ($record->status_financeiro == true) {
                            ContasReceber::where('locacao_id', $record->id)->delete();
                            FluxoCaixa::where('locacao_id', $record->id)->delete();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->deferLoading()
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLocacaos::route('/'),
        ];
    }
}
