# Documentação Técnica da API

## Visão Geral

A API do Sistema de Entrega de Assinaturas de Pães é uma API RESTful construída em Laravel que fornece endpoints para gerenciar usuários, assinaturas, entregas e pagamentos.

### Base URL
```
Desenvolvimento: http://localhost:8000/api
Produção: https://api.breaddelivery.com/api
```

### Autenticação

A API utiliza autenticação Bearer Token via Laravel Sanctum.

#### Headers Obrigatórios
```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

## Endpoints de Autenticação

### POST /auth/register
Registra um novo usuário no sistema.

**Parâmetros:**
```json
{
    "name": "string (required, max:255)",
    "email": "string (required, email, unique)",
    "password": "string (required, min:8, confirmed)",
    "password_confirmation": "string (required)",
    "phone": "string (nullable, max:20)",
    "cpf": "string (nullable, max:14)"
}
```

**Resposta de Sucesso (201):**
```json
{
    "success": true,
    "message": "Usuário registrado com sucesso",
    "data": {
        "user": {
            "id": 1,
            "name": "João Silva",
            "email": "joao@email.com",
            "phone": "(11) 99999-9999",
            "cpf": "123.456.789-00",
            "role": "customer",
            "created_at": "2024-01-15T10:30:00.000000Z"
        },
        "token": "1|abc123def456..."
    }
}
```

### POST /auth/login
Autentica um usuário existente.

**Parâmetros:**
```json
{
    "email": "string (required, email)",
    "password": "string (required)"
}
```

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "message": "Login realizado com sucesso",
    "data": {
        "user": {
            "id": 1,
            "name": "João Silva",
            "email": "joao@email.com",
            "role": "customer"
        },
        "token": "2|xyz789abc123..."
    }
}
```

### POST /auth/logout
Revoga o token atual do usuário.

**Headers:** `Authorization: Bearer {token}`

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "message": "Logout realizado com sucesso"
}
```

### GET /auth/me
Retorna os dados do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "João Silva",
        "email": "joao@email.com",
        "phone": "(11) 99999-9999",
        "cpf": "123.456.789-00",
        "role": "customer",
        "asaas_customer_id": "cus_abc123",
        "created_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

## Endpoints de Planos

### GET /plans
Lista todos os planos ativos.

**Parâmetros de Query:**
- `frequency` (opcional): Filtrar por frequência (daily, alternate_days, weekends, weekly, monthly)
- `max_price` (opcional): Preço máximo
- `min_price` (opcional): Preço mínimo

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Plano Diário",
            "description": "Pão fresco todos os dias",
            "price": 15.90,
            "frequency": "daily",
            "bread_types": ["francês", "integral"],
            "is_active": true,
            "formatted_price": "R$ 15,90",
            "frequency_description": "Todos os dias"
        }
    ]
}
```

### GET /plans/{id}
Retorna detalhes de um plano específico.

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Plano Diário",
        "description": "Pão fresco todos os dias",
        "price": 15.90,
        "frequency": "daily",
        "bread_types": ["francês", "integral"],
        "delivery_days": [1, 2, 3, 4, 5, 6, 0],
        "is_active": true,
        "subscription_count": 150,
        "average_rating": 4.8
    }
}
```

## Endpoints de Endereços

### GET /addresses
Lista endereços do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "street": "Rua das Flores, 123",
            "neighborhood": "Centro",
            "city": "São Paulo",
            "state": "SP",
            "postal_code": "01234-567",
            "complement": "Apto 45",
            "reference": "Próximo ao mercado",
            "is_default": true,
            "latitude": -23.5505,
            "longitude": -46.6333
        }
    ]
}
```

### POST /addresses
Cria um novo endereço.

**Headers:** `Authorization: Bearer {token}`

**Parâmetros:**
```json
{
    "street": "string (required, max:255)",
    "neighborhood": "string (required, max:100)",
    "city": "string (required, max:100)",
    "state": "string (required, max:2)",
    "postal_code": "string (required, max:10)",
    "complement": "string (nullable, max:255)",
    "reference": "string (nullable, max:255)",
    "is_default": "boolean (nullable)"
}
```

**Resposta de Sucesso (201):**
```json
{
    "success": true,
    "message": "Endereço criado com sucesso",
    "data": {
        "id": 2,
        "street": "Av. Paulista, 1000",
        "neighborhood": "Bela Vista",
        "city": "São Paulo",
        "state": "SP",
        "postal_code": "01310-100",
        "is_default": false
    }
}
```

## Endpoints de Assinaturas

### GET /subscriptions
Lista assinaturas do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Parâmetros de Query:**
- `status` (opcional): Filtrar por status (active, paused, cancelled, suspended)
- `plan_id` (opcional): Filtrar por plano

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "plan": {
                "id": 1,
                "name": "Plano Diário",
                "price": 15.90
            },
            "address": {
                "id": 1,
                "street": "Rua das Flores, 123",
                "neighborhood": "Centro"
            },
            "status": "active",
            "start_date": "2024-01-15",
            "next_billing_date": "2024-02-15",
            "next_delivery_date": "2024-01-16",
            "preferred_payment_method": "pix",
            "delivery_preferences": {
                "time_preference": "morning",
                "special_instructions": "Deixar na portaria"
            },
            "created_at": "2024-01-15T10:30:00.000000Z"
        }
    ]
}
```

### POST /subscriptions
Cria uma nova assinatura.

**Headers:** `Authorization: Bearer {token}`

**Parâmetros:**
```json
{
    "plan_id": "integer (required, exists:plans,id)",
    "address_id": "integer (required, exists:addresses,id)",
    "start_date": "date (required, after_or_equal:today)",
    "preferred_payment_method": "string (required, in:pix,boleto)",
    "delivery_preferences": {
        "time_preference": "string (nullable, in:morning,afternoon,evening)",
        "special_instructions": "string (nullable, max:500)"
    }
}
```

**Resposta de Sucesso (201):**
```json
{
    "success": true,
    "message": "Assinatura criada com sucesso",
    "data": {
        "id": 2,
        "plan_id": 1,
        "address_id": 1,
        "status": "active",
        "start_date": "2024-01-20",
        "asaas_subscription_id": "sub_abc123",
        "first_payment": {
            "id": 1,
            "amount": 15.90,
            "due_date": "2024-01-20",
            "pix_qr_code": "data:image/png;base64,iVBOR...",
            "pix_copy_paste": "00020126..."
        }
    }
}
```

### POST /subscriptions/{id}/pause
Pausa uma assinatura.

**Headers:** `Authorization: Bearer {token}`

**Parâmetros:**
```json
{
    "reason": "string (nullable, max:255)",
    "resume_date": "date (nullable, after:today)"
}
```

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "message": "Assinatura pausada com sucesso",
    "data": {
        "id": 1,
        "status": "paused",
        "paused_at": "2024-01-16T14:30:00.000000Z",
        "pause_reason": "Viagem",
        "resume_date": "2024-02-01"
    }
}
```

## Endpoints de Entregas

### GET /deliveries
Lista entregas do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

**Parâmetros de Query:**
- `status` (opcional): Filtrar por status (pending, in_transit, completed, failed)
- `start_date` (opcional): Data inicial
- `end_date` (opcional): Data final
- `subscription_id` (opcional): Filtrar por assinatura

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "subscription": {
                "id": 1,
                "plan": {
                    "name": "Plano Diário"
                }
            },
            "status": "completed",
            "scheduled_date": "2024-01-16",
            "scheduled_time": "08:00:00",
            "delivered_at": "2024-01-16T08:15:00.000000Z",
            "delivery_address": "Rua das Flores, 123",
            "customer_rating": 5,
            "customer_feedback": "Entrega perfeita!",
            "delivery_notes": "Entregue na portaria"
        }
    ]
}
```

### POST /deliveries/{id}/rate
Avalia uma entrega.

**Headers:** `Authorization: Bearer {token}`

**Parâmetros:**
```json
{
    "rating": "integer (required, min:1, max:5)",
    "feedback": "string (nullable, max:500)"
}
```

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "message": "Avaliação registrada com sucesso",
    "data": {
        "id": 1,
        "customer_rating": 5,
        "customer_feedback": "Entrega perfeita!",
        "rated_at": "2024-01-16T20:30:00.000000Z"
    }
}
```

## Endpoints Administrativos

### GET /admin/dashboard/overview
Retorna estatísticas gerais do sistema.

**Headers:** `Authorization: Bearer {token}` (Admin)

**Parâmetros de Query:**
- `period` (opcional): today, week, month, quarter, year
- `start_date` (opcional): Data inicial personalizada
- `end_date` (opcional): Data final personalizada

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": {
        "users": {
            "total": 1250,
            "new": 45,
            "active": 980,
            "inactive": 270
        },
        "subscriptions": {
            "total": 850,
            "active": 720,
            "suspended": 80,
            "cancelled": 50,
            "new": 25
        },
        "deliveries": {
            "total": 15600,
            "completed": 14950,
            "pending": 450,
            "failed": 200,
            "completion_rate": 95.83,
            "average_rating": 4.7
        },
        "payments": {
            "total": 2100,
            "paid": 1950,
            "pending": 120,
            "overdue": 20,
            "failed": 10,
            "success_rate": 92.86
        },
        "revenue": {
            "total": 31050.50,
            "average_ticket": 15.92,
            "transaction_count": 1950,
            "growth_percentage": 12.5,
            "formatted_total": "R$ 31.050,50"
        }
    },
    "period": "month",
    "date_range": {
        "start": "2024-01-01",
        "end": "2024-01-31"
    }
}
```

### GET /admin/dashboard/analytics
Retorna dados analíticos detalhados.

**Headers:** `Authorization: Bearer {token}` (Admin)

**Parâmetros de Query:**
- `metric` (obrigatório): revenue, subscriptions, deliveries, customers
- `period` (opcional): daily, weekly, monthly
- `start_date` (opcional): Data inicial
- `end_date` (opcional): Data final
- `limit` (opcional): Limite de registros (1-365)

**Resposta de Sucesso (200):**
```json
{
    "success": true,
    "data": [
        {
            "period": "2024-01-15",
            "revenue": 1250.50,
            "transaction_count": 78,
            "formatted_revenue": "R$ 1.250,50"
        },
        {
            "period": "2024-01-16",
            "revenue": 1380.75,
            "transaction_count": 85,
            "formatted_revenue": "R$ 1.380,75"
        }
    ],
    "metric": "revenue",
    "period": "daily"
}
```

## Códigos de Status HTTP

### Sucesso
- `200 OK`: Requisição bem-sucedida
- `201 Created`: Recurso criado com sucesso
- `204 No Content`: Operação bem-sucedida sem conteúdo de retorno

### Erro do Cliente
- `400 Bad Request`: Dados inválidos na requisição
- `401 Unauthorized`: Token de autenticação inválido ou ausente
- `403 Forbidden`: Usuário não tem permissão para acessar o recurso
- `404 Not Found`: Recurso não encontrado
- `422 Unprocessable Entity`: Erro de validação

### Erro do Servidor
- `500 Internal Server Error`: Erro interno do servidor

## Estrutura de Resposta de Erro

```json
{
    "success": false,
    "message": "Mensagem de erro",
    "errors": {
        "campo": ["Mensagem de validação específica"]
    }
}
```

## Rate Limiting

A API implementa rate limiting para prevenir abuso:

- **Autenticação**: 5 tentativas por minuto por IP
- **API Geral**: 60 requisições por minuto por usuário autenticado
- **Webhooks**: Sem limite (verificação por assinatura)

## Webhooks

### POST /webhooks/asaas
Endpoint para receber webhooks do Asaas.

**Headers:**
- `Asaas-Signature`: Assinatura do webhook para verificação

**Eventos Suportados:**
- `PAYMENT_CREATED`: Pagamento criado
- `PAYMENT_RECEIVED`: Pagamento recebido
- `PAYMENT_CONFIRMED`: Pagamento confirmado
- `PAYMENT_OVERDUE`: Pagamento vencido
- `PAYMENT_REFUNDED`: Pagamento reembolsado

## Versionamento

A API utiliza versionamento via header:

```http
Accept: application/vnd.breaddelivery.v1+json
```

Versões disponíveis:
- `v1`: Versão atual (padrão)

## Ambientes

### Desenvolvimento
- Base URL: `http://localhost:8000/api`
- Banco: SQLite
- Asaas: Sandbox

### Produção
- Base URL: `https://api.breaddelivery.com/api`
- Banco: MySQL/PostgreSQL
- Asaas: Produção

## Exemplos de Uso

### Fluxo Completo de Assinatura

1. **Registrar usuário**
2. **Fazer login**
3. **Criar endereço**
4. **Listar planos**
5. **Criar assinatura**
6. **Acompanhar entregas**

### Exemplo com cURL

```bash
# 1. Registrar usuário
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@email.com",
    "password": "senha123",
    "password_confirmation": "senha123"
  }'

# 2. Fazer login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao@email.com",
    "password": "senha123"
  }'

# 3. Listar planos (usando token do login)
curl -X GET http://localhost:8000/api/plans \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

## Suporte

Para dúvidas sobre a API:
- **Email**: dev@breaddelivery.com
- **Documentação**: [docs.breaddelivery.com](https://docs.breaddelivery.com)
- **Postman Collection**: [Download](https://docs.breaddelivery.com/postman)

