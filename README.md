# KNN Idiomas Barretos — Landing Page

Recriação da landing page da divulgação **37061**, mantendo o envio dos leads para o DRIVE da KNN Barretos e deixando o front-end sob nosso controle.

## Visualizar no GitHub Codespaces

1. Abra este repositório no GitHub.
2. Clique em **Code > Codespaces > Create codespace on main**.
3. O Codespace iniciará o servidor automaticamente na porta **3000**.
4. A porta 3000 está configurada para abrir o preview da landing page.

Se o preview não abrir sozinho, no terminal execute:

```bash
npm start
```

Depois abra a porta **3000** na aba **Ports** do Codespace.

## Integração DRIVE

O formulário envia os leads para:

```text
POST https://drive.knnidiomas.com.br/api/v1/parceria-cupons/landingpage-cupom/
```

Payload preservado:

```json
{
  "cda_id": null,
  "email": "lead@email.com",
  "idade": "26",
  "nome": "Nome do Lead",
  "parceria_id": 37061,
  "status_id": 1,
  "telefone": "(17) 99999-9999"
}
```

O navegador envia primeiro para `/api/lead` no nosso servidor Node. O `server.js` encaminha o cadastro para o DRIVE. Isso evita dependência de CORS no navegador.

## Performance

A página já envia eventos para `window.dataLayer`:

- `form_start`
- `lead`
- `lead_error`
- `whatsapp_click`

Assim podemos instalar depois GTM, GA4, Meta Pixel e Clarity sem alterar a integração principal.

## Atenção

Antes de usar em campanhas, faça um lead de teste no preview e confirme que ele aparece no DRIVE da KNN Barretos.
