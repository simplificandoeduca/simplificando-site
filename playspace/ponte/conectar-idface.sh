#!/bin/bash
# =====================================================================
#  Play Space — Conectar o iDFace (Mac)
#  Roda uma vez, num Mac na mesma rede do aparelho: acha o iDFace, entra nele com o usuário e a senha do menu,
#  liga o modo push apontando para o site e espera o site confirmar o primeiro contato. Depois disso o aparelho
#  fala sozinho com o site pela internet; este computador não precisa de mais nada.
#  O que vem do site já está preenchido abaixo (o site gera este arquivo em Ajustes → iDFace).
# =====================================================================
SITE='__SITE__'
CHAVE='__CHAVE__'
IP='__IP__'
USUARIO='__USUARIO__'
NOME='__NOME__'

C0=$'\033[0m'; AZ=$'\033[36m'; VD=$'\033[32m'; AM=$'\033[33m'; VM=$'\033[31m'; NG=$'\033[1m'
diz() { printf '%s\n' "$*"; }
fim() { # $1 = ok|erro, $2 = mensagem
  diz ''
  if [ "$1" = ok ]; then diz "${VD}${NG}CONCLUÍDO${C0} — $2"; else diz "${VM}${NG}NÃO DEU${C0} — $2"; fi
  diz ''
  read -r -p 'Pressione Enter para fechar. ' _
  exit 0
}
clear
diz "${NG}Play Space — conectar o iDFace ao site${C0}"
diz '---------------------------------------'
diz 'Este Mac precisa estar na MESMA rede (mesmo roteador) do iDFace, e o iDFace precisa de internet.'
diz ''
[ -z "$SITE" ] || [ -z "$CHAVE" ] && fim erro 'este arquivo veio sem o endereço do site. Baixe de novo em Play Space → Ajustes → iDFace.'

# ---------- 1. achar o iDFace na rede ----------
testa_idface() { # devolve 0 se o endereço responde como um iDFace (login.fcgi da Control iD)
  local r; r=$(curl -s -m 2 -X POST "http://$1/login.fcgi" -H 'Content-Type: application/json' -d '{"login":"play-procura","password":"x"}' 2>/dev/null)
  case "$r" in
    \{*\"session\"*|\{*\"error\"*) return 0 ;;
  esac
  [ ${#r} -lt 200 ] && case "$r" in *"Invalid login"*) return 0 ;; esac
  return 1
}
if [ -n "$IP" ] && testa_idface "$IP"; then
  diz "${VD}✓${C0} iDFace encontrado em $IP (o endereço salvo em Ajustes)."
else
  [ -n "$IP" ] && diz "${AM}O endereço salvo ($IP) não respondeu como iDFace. Vou procurar na rede.${C0}"
  IP=''
  diz "${AZ}Procurando o iDFace na rede deste Mac (uns 10 segundos)…${C0}"
  tmp=$(mktemp -d); : > "$tmp/achados"
  redes=$(ifconfig 2>/dev/null | awk '/inet / && $2 !~ /^127\./ && $2 !~ /^169\.254/ {print $2}' | sed 's/\.[0-9]*$//' | sort -u)
  for rede in $redes; do
    n=1
    while [ $n -le 254 ]; do
      ( testa_idface "$rede.$n" && echo "$rede.$n" >> "$tmp/achados" ) &
      n=$((n + 1))
      [ $((n % 60)) -eq 0 ] && sleep 0.3
    done
  done
  wait
  IP=$(head -n 1 "$tmp/achados" 2>/dev/null); rm -rf "$tmp"
  if [ -n "$IP" ]; then diz "${VD}✓${C0} Achei o iDFace em $IP."; else
    diz "${AM}Não achei o aparelho sozinho.${C0} Ele está no cabo de rede do mesmo roteador deste Mac? No visor: Menu → Configurações → Rede mostra o IP (e deixe o DHCP ligado)."
    read -r -p 'IP do iDFace: ' IP
    [ -z "$IP" ] && fim erro 'sem o IP não dá para continuar.'
    testa_idface "$IP" || fim erro "$IP não respondeu como um iDFace. Confira: (1) o iDFace está no CABO de rede do mesmo roteador deste Mac? (2) no visor dele, em Menu → Configurações → Rede, o DHCP está ligado? Ligue, toque em Ok e rode de novo."
  fi
fi

# ---------- 2. entrar no aparelho ----------
[ -z "$USUARIO" ] && USUARIO=admin
diz ''
read -r -p "Usuário do iDFace [$USUARIO]: " u; [ -n "$u" ] && USUARIO=$u
read -r -s -p 'Senha do iDFace (de fábrica costuma ser admin): ' SENHA; diz ''
esc() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'; }
resp=$(curl -s -m 8 -X POST "http://$IP/login.fcgi" -H 'Content-Type: application/json' -d "{\"login\":\"$(esc "$USUARIO")\",\"password\":\"$(esc "$SENHA")\"}")
SESSAO=$(printf '%s' "$resp" | sed -n 's/.*"session" *: *"\([^"]*\)".*/\1/p')
[ -z "$SESSAO" ] && fim erro 'o iDFace recusou o usuário ou a senha. Use os mesmos que entram no menu do aparelho e rode de novo.'
diz "${VD}✓${C0} Entrei no iDFace."

# ---------- 3. ligar o modo push apontando para o site ----------
PUSH="${SITE}/${CHAVE}"
r=$(curl -s -m 10 -X POST "http://$IP/set_configuration.fcgi?session=$SESSAO" -H 'Content-Type: application/json' \
  -d "{\"push_server\":{\"push_remote_address\":\"$PUSH\",\"push_request_period\":\"5\",\"push_request_timeout\":\"15000\"}}")
case "$r" in *\"error\"*) fim erro "o iDFace não aceitou ligar o modo push: $r. Mande uma foto desta janela para o suporte." ;; esac
diz "${VD}✓${C0} Modo push ligado: o aparelho vai chamar o site a cada 5 segundos."
curl -s -m 5 -X POST "http://$IP/logout.fcgi?session=$SESSAO" >/dev/null 2>&1

# ---------- 4. esperar o site confirmar ----------
diz "${AZ}Esperando o site confirmar o primeiro contato do aparelho (até 3 minutos)…${C0}"
i=0
while [ $i -lt 36 ]; do
  r=$(curl -s -m 15 -X POST "$SITE" -H 'Content-Type: application/json' -H "X-Ponte-Chave: $CHAVE" \
    -d "{\"acao\":\"ponte\",\"nome\":\"$(esc "$NOME") (Mac)\",\"versao\":\"mac-1\",\"batidas\":[]}")
  case "$r" in
    *\"desligado\":true*) fim erro 'o iDFace está desligado em Play Space → Ajustes → iDFace. Ligue a leitura lá e rode de novo.' ;;
    *\"direto_ok\":true*) fim ok 'o iDFace já manda os rostos sozinho para o site. Pode fechar esta janela; este Mac não precisa de mais nada.' ;;
    *\"erro\"*) fim erro "o site recusou a chave. Em Ajustes → iDFace, baixe este arquivo de novo. ($r)" ;;
  esac
  printf '.'; sleep 5; i=$((i + 1))
done
fim erro 'em 3 minutos o site não recebeu o aparelho. Confira se o iDFace está no cabo com internet (ele precisa alcançar o site) e tente de novo.'
