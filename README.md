# Sistema de Entrega de Assinaturas de Pães

Um sistema completo e escalável para gestão de assinaturas de entrega de pães, desenvolvido em Laravel com integração ao Asaas para pagamentos recorrentes.

## 🚀 Características Principais

### 🔐 Autenticação e Gestão de Usuários
- Sistema de autenticação completo com Laravel Sanctum
- Gestão de perfis de usuários (clientes e administradores)
- Middleware personalizado para controle de acesso por roles
- Atualização de perfil e alteração de senhas

### 📦 Gestão de Assinaturas
- Múltiplos planos de assinatura (diário, dias alternados, fins de semana, semanal, mensal)
- Criação automática de assinaturas com integração Asaas
- Gestão completa de status (ativo, pausado, cancelado, suspenso)
- Histórico completo de pagamentos e entregas

### 🚚 Sistema de Entregas
- Geração automática de entregas baseada nas assinaturas ativas
- Planejamento de rotas diárias por bairro
- Sistema de avaliação e feedback dos clientes
- Rastreamento completo com coordenadas GPS
- Calendário de entregas para clientes

### 💳 Integração com Asaas
- Criação automática de clientes no Asaas
- Gestão de pagamentos recorrentes (PIX, Boleto)
- Webhooks para processamento em tempo real
- QR Code PIX e links de pagamento automáticos

### 📊 Painel Administrativo
- Dashboard completo com métricas em tempo real
- Analytics detalhados de receita, assinaturas e entregas
- Exportação de dados em CSV/XLSX
- Atividades recentes e top planos
- Relatórios personalizáveis por período

### 🔄 Processamento Assíncrono
- Sistema de filas Laravel para alta performance
- Jobs para notificações automáticas
- Renovação automática de assinaturas
- Geração automática de entregas
- Logs detalhados para monitoramento

## 🛠️ Tecnologias Utilizadas

- **Backend**: Laravel 10.x (PHP 8.1+)
- **Banco de Dados**: SQLite (desenvolvimento) / MySQL/PostgreSQL (produção)
- **Autenticação**: Laravel Sanctum
- **Pagamentos**: API Asaas
- **Filas**: Laravel Queue (Database driver)
- **Logs**: Laravel Log
- **Validação**: Laravel Validation
- **Testes**: PHPUnit

## 📋 Pré-requisitos

- PHP 8.1 ou superior
- Composer
- SQLite (desenvolvimento) ou MySQL/PostgreSQL (produção)
- Conta no Asaas (para pagamentos)

## 🚀 Instalação

### 1. Clone o repositório
```bash
git clone <repository-url>
cd bread-subscription-system
```

### 2. Instale as dependências
```bash
composer install
```

### 3. Configure o ambiente
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure o banco de dados
Edite o arquivo `.env` com suas configurações:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

### 5. Execute as migrations
```bash
php artisan migrate
```

### 6. Configure as filas
```bash
php artisan queue:table
php artisan migrate
```

### 7. Inicie o servidor
```bash
php artisan serve
```

### 8. Inicie o worker de filas (em outro terminal)
```bash
php artisan queue:work
```

## ⚙️ Configuração do Asaas

### 1. Obtenha suas credenciais
- Acesse [Asaas](https://www.asaas.com)
- Obtenha sua API Key (sandbox ou produção)
- Configure o webhook secret

### 2. Configure no .env
```env
ASAAS_API_KEY=your_api_key_here
ASAAS_ENVIRONMENT=sandbox
ASAAS_WEBHOOK_SECRET=your_webhook_secret_here
```

### 3. Configure o webhook no Asaas
- URL: `https://seu-dominio.com/api/webhooks/asaas`
- Eventos: Todos os eventos de pagamento

## 📚 Documentação da API

### Autenticação

#### Registro
```http
POST /api/auth/register
Content-Type: application/json

{
    "name": "João Silva",
    "email": "joao@email.com",
    "password": "senha123",
    "password_confirmation": "senha123",
    "phone": "(11) 99999-9999",
    "cpf": "123.456.789-00"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "joao@email.com",
    "password": "senha123"
}
```

### Planos

#### Listar planos
```http
GET /api/plans
Authorization: Bearer {token}
```

#### Criar plano (Admin)
```http
POST /api/admin/plans
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Plano Diário",
    "description": "Pão fresco todos os dias",
    "price": 15.90,
    "frequency": "daily",
    "bread_types": ["francês", "integral"]
}
```

### Assinaturas

#### Criar assinatura
```http
POST /api/subscriptions
Authorization: Bearer {token}
Content-Type: application/json

{
    "plan_id": 1,
    "address_id": 1,
    "start_date": "2024-01-15",
    "preferred_payment_method": "pix",
    "delivery_preferences": {
        "time_preference": "morning",
        "special_instructions": "Deixar na portaria"
    }
}
```

#### Pausar assinatura
```http
POST /api/subscriptions/{id}/pause
Authorization: Bearer {token}
Content-Type: application/json

{
    "reason": "Viagem",
    "resume_date": "2024-02-01"
}
```

### Entregas

#### Listar entregas
```http
GET /api/deliveries
Authorization: Bearer {token}
```

#### Avaliar entrega
```http
POST /api/deliveries/{id}/rate
Authorization: Bearer {token}
Content-Type: application/json

{
    "rating": 5,
    "feedback": "Entrega perfeita, pão quentinho!"
}
```

### Dashboard Administrativo

#### Visão geral
```http
GET /api/admin/dashboard/overview?period=month
Authorization: Bearer {token}
```

#### Analytics
```http
GET /api/admin/dashboard/analytics?metric=revenue&period=daily&limit=30
Authorization: Bearer {token}
```

## 🏗️ Arquitetura do Sistema

### Estrutura de Domínios

```
app/
├── Domains/
│   ├── Auth/           # Autenticação e usuários
│   ├── Subscription/   # Assinaturas e planos
│   ├── Delivery/       # Entregas e logística
│   └── Payment/        # Pagamentos e Asaas
├── Http/
│   ├── Controllers/    # Controllers da API
│   └── Middleware/     # Middlewares personalizados
├── Jobs/              # Jobs assíncronos
├── Models/            # Modelos Eloquent
└── Services/          # Services e integrações
```

### Modelos Principais

- **User**: Usuários do sistema (clientes e admins)
- **Plan**: Planos de assinatura disponíveis
- **Subscription**: Assinaturas dos clientes
- **Address**: Endereços de entrega
- **Delivery**: Entregas agendadas e realizadas
- **Payment**: Pagamentos e transações

### Jobs Assíncronos

- **GenerateDeliveries**: Gera entregas baseadas nas assinaturas
- **SendPaymentNotification**: Envia notificações de pagamento
- **SendDeliveryNotification**: Envia notificações de entrega
- **ProcessSubscriptionRenewal**: Processa renovações automáticas

## 🔒 Segurança

### Autenticação
- Tokens JWT via Laravel Sanctum
- Middleware de verificação de roles
- Rate limiting nas rotas de autenticação

### Validação
- Validação rigorosa de todos os inputs
- Sanitização de dados
- Verificação de permissões por endpoint

### Webhooks
- Verificação de assinatura dos webhooks Asaas
- Logs de segurança para tentativas inválidas

## 📈 Escalabilidade

### Performance
- Uso de filas para processamento assíncrono
- Índices otimizados no banco de dados
- Cache de consultas frequentes

### Arquitetura
- Separação clara de responsabilidades
- Services para lógica de negócio
- Repositories para acesso a dados
- Events e Listeners para desacoplamento

### Multi-tenancy (Futuro)
- Estrutura preparada para múltiplas empresas
- Isolamento de dados por tenant
- Configurações personalizáveis

## 🧪 Testes

### Executar testes
```bash
php artisan test
```

### Cobertura de testes
```bash
php artisan test --coverage
```

### Tipos de testes
- **Unit Tests**: Testes unitários dos models e services
- **Feature Tests**: Testes de integração da API
- **Browser Tests**: Testes end-to-end (Laravel Dusk)

## 📊 Monitoramento

### Logs
- Logs estruturados para todas as operações
- Níveis de log configuráveis
- Integração com serviços de monitoramento

### Métricas
- Dashboard de métricas em tempo real
- Alertas para falhas de pagamento
- Monitoramento de performance das entregas

## 🚀 Deploy

### Ambiente de Produção

1. **Servidor Web**: Nginx ou Apache
2. **PHP**: 8.1+ com extensões necessárias
3. **Banco**: MySQL 8.0+ ou PostgreSQL 13+
4. **Redis**: Para cache e filas (recomendado)
5. **Supervisor**: Para gerenciar workers de fila

### Configurações de Produção

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### Workers de Fila
```bash
# Supervisor configuration
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está licenciado sob a Licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 📞 Suporte

Para suporte técnico ou dúvidas sobre o sistema:

- **Email**: suporte@breaddelivery.com
- **Documentação**: [docs.breaddelivery.com](https://docs.breaddelivery.com)
- **Issues**: [GitHub Issues](https://github.com/seu-usuario/bread-subscription-system/issues)

## 🎯 Roadmap

### Versão 2.0
- [ ] App mobile para clientes
- [ ] App mobile para entregadores
- [ ] Integração com outros gateways de pagamento
- [ ] Sistema de cupons e descontos
- [ ] Programa de fidelidade

### Versão 3.0
- [ ] IA para otimização de rotas
- [ ] Previsão de demanda
- [ ] Sistema de recomendações
- [ ] Multi-idiomas
- [ ] API pública para parceiros

---

**Desenvolvido com ❤️ para revolucionar a entrega de pães frescos!**

