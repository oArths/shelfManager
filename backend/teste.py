import jwt
import bcrypt
import sqlite3
import httpx
from typing import TypedDict, Optional, List
from pathlib import Path
from jwt import PyJWTError
from datetime import datetime
from pytz import InvalidTimeError
from fastapi.middleware.cors import CORSMiddleware
from fastapi import FastAPI, HTTPException, Depends, Query, Request, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel

app = FastAPI(title="Shelf Manager API", version="1.0.0")
DB_PATH = "./db/database.db"
SCHEMA_PATH = "./db/schema.sql"
WORDPRESS_API_URL = "https://lebrecho.com.br/wp-json/shelf-products/v1"
WORDPRESS_API_KEY = "SHELF_MANAGER_2024"  # A mesma do WordPress
SECRET_KEY = "SHELF_MANAGER_2024"
ALGORITHM = "HS256"
security = HTTPBearer()

allow_origins = [
    "http://localhost:5173",
    "http://127.0.0.1:5173"
]

# ==================== CORS ====================
app.add_middleware(
    CORSMiddleware,
    allow_origins=allow_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ==================== MODELS ====================


class UserCreate(BaseModel):
    username: str
    password: str
    isAdmin: bool = False


class UserLogin(BaseModel):
    username: str
    password: str


class Token(BaseModel):
    access_token: str
    token_type: str


class ShelfCreate(BaseModel):
    name: str
    description: Optional[str] = None


class ShelfUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None


class ShelfResponse(BaseModel):
    id: int
    name: str
    description: Optional[str]
    created_by: int
    item_count: int
    created_at: str
    updated_at: str


class ProductReference(BaseModel):
    id: int
    name: str
    sku: Optional[str]
    price: float
    stock: Optional[int]
    main_image: Optional[str]
    product_url: Optional[str]


class ShelfItemAdd(BaseModel):
    product_id: int
    quantity: int = 1


class ShelfItemResponse(BaseModel):
    id: int
    shelf_id: int
    product_id: int
    quantity: int
    added_at: str
    product_data: Optional[ProductReference] = None


class ShelfItemMove(BaseModel):
    product_id: int
    to_shelf_id: int


class WordpressProduct(TypedDict):
    id: int
    name: str
    sku: Optional[str]
    price: float
    stock: Optional[int]
    main_image: Optional[str]
    product_url: Optional[str]


# ==================== FUNÇÕES DE BANCO E AUTENTICAÇÃO ====================


def init_db():
    """Inicializa o banco de dados"""
    Path("db").mkdir(exist_ok=True)

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys = ON")  # Ativa foreign keys
    cur = conn.cursor()

    with open(SCHEMA_PATH, "r", encoding="utf-8") as f:
        schema = f.read()
        cur.executescript(schema)

    conn.commit()
    conn.close()


def create_admin():
    """Cria usuário admin padrão"""
    conn = get_db()
    cur = conn.cursor()

    # Verifica se admin já existe
    cur.execute("SELECT id FROM users WHERE username = 'admin'")
    if not cur.fetchone():
        hashed = hash_password("123456")
        cur.execute(
            "INSERT INTO users (username, password_hash, isAdmin) VALUES (?, ?, ?)",
            ("admin", hashed, True),
        )
        conn.commit()

    conn.close()


def hash_password(password: str) -> str:
    """Hash da senha usando bcrypt"""
    salt = bcrypt.gensalt()
    return bcrypt.hashpw(password.encode(), salt).decode()


def verify_password(password: str, password_hash: str) -> bool:
    """Verifica senha com hash"""
    return bcrypt.checkpw(password.encode(), password_hash.encode())


def create_token(username: str, user_id: int, is_admin: bool) -> str:
    """Cria token JWT"""
    payload = {
        "sub": username,
        "id": user_id,
        "isAdmin": is_admin,
        "exp": datetime.utcnow().timestamp() + 3600,  # 1 hora
    }
    return jwt.encode(payload, SECRET_KEY, algorithm=ALGORITHM)


def verify_token(credentials: HTTPAuthorizationCredentials = Depends(security)):
    """Verifica token JWT"""
    token = credentials.credentials

    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        return payload
    except InvalidTimeError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado",
        )


def get_current_user(payload: dict = Depends(verify_token)):
    """Obtém usuário atual do token"""
    return {
        "id": payload["id"],
        "username": payload["sub"],
        "isAdmin": payload["isAdmin"],
    }


# ==================== ENDPOINTS DE AUTENTICAÇÃO ====================


@app.post("/auth/register", response_model=dict)
def register_user(user: UserCreate):
    """Registra novo usuário"""
    conn = get_db()
    cur = conn.cursor()

    # Verifica se usuário já existe
    cur.execute("SELECT id FROM users WHERE username = ?", (user.username,))
    if cur.fetchone():
        conn.close()
        raise HTTPException(status_code=400, detail="Usuário já existe")

    # Cria usuário
    hashed_password = hash_password(user.password)
    cur.execute(
        """INSERT INTO users (username, password_hash, isAdmin) 
           VALUES (?, ?, ?)""",
        (user.username, hashed_password, user.isAdmin),
    )
    user_id = cur.lastrowid

    conn.commit()

    # Retorna usuário criado
    cur.execute(
        "SELECT id, username, isAdmin, created_at FROM users WHERE id = ?", (user_id,)
    )
    user_data = cur.fetchone()
    conn.close()

    return {
        "success": True,
        "message": "Usuário criado com sucesso",
        "data": dict(user_data),
    }


@app.post("/auth/login", response_model=Token)
def login(user: UserLogin):
    """Login e geração de token"""
    conn = get_db()
    cur = conn.cursor()

    cur.execute(
        "SELECT id, username, password_hash, isAdmin FROM users WHERE username = ?",
        (user.username,),
    )
    user_data = cur.fetchone()
    conn.close()

    if not user_data or not verify_password(user.password, user_data["password_hash"]):
        raise HTTPException(status_code=401, detail="Credenciais inválidas")

    token = create_token(
        username=user_data["username"],
        user_id=user_data["id"],
        is_admin=bool(user_data["isAdmin"]),
    )

    return {"access_token": token, "token_type": "bearer"}


# ==================== ENDPOINTS DE PRATELEIRAS ====================


@app.get("/shelves", response_model=List[ShelfResponse])
def get_shelves(current_user: dict = Depends(get_current_user)):
    conn = get_db()
    cur = conn.cursor()

    cur.execute("""
        SELECT s.*, 
               COUNT(si.id) as item_count,
               u.username as created_by_name
        FROM shelves s
        LEFT JOIN shelf_items si ON s.id = si.shelf_id
        LEFT JOIN users u ON s.created_by = u.id
        GROUP BY s.id
        ORDER BY s.created_at DESC
    """)

    shelves = []
    for row in cur.fetchall():
        shelves.append(dict(row))

    conn.close()
    return shelves


@app.get("/shelves/{shelf_id}", response_model=ShelfResponse)
def get_shelf(shelf_id: int, current_user: dict = Depends(get_current_user)):
    conn = get_db()
    cur = conn.cursor()

    cur.execute("""
        SELECT s.*, 
               COUNT(si.id) as item_count,
               u.username as created_by_name
        FROM shelves s
        LEFT JOIN shelf_items si ON s.id = si.shelf_id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = ?
        GROUP BY s.id
    """, (shelf_id,))

    shelf = cur.fetchone()
    conn.close()

    if not shelf:
        raise HTTPException(status_code=404, detail="Prateleira não encontrada")

    return dict(shelf)


@app.post("/shelves", response_model=ShelfResponse)
def create_shelf(shelf: ShelfCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db()
    cur = conn.cursor()

    # Verifica se já existe prateleira com mesmo nome
    cur.execute("SELECT id FROM shelves WHERE name = ?", (shelf.name,))
    if cur.fetchone():
        conn.close()
        raise HTTPException(
            status_code=400, detail="Prateleira com este nome já existe"
        )

    # Cria prateleira
    cur.execute(
        """
        INSERT INTO shelves (name, description, created_by)
        VALUES (?, ?, ?)
    """,
        (shelf.name, shelf.description, current_user["id"]),
    )

    shelf_id = cur.lastrowid
    conn.commit()

    # Retorna prateleira criada
    cur.execute(
        """
        SELECT s.*, 0 as item_count
        FROM shelves s
        WHERE s.id = ?
    """,
        (shelf_id,),
    )

    shelf_data = dict(cur.fetchone())
    conn.close()

    return shelf_data


@app.put("/shelves/{shelf_id}", response_model=ShelfResponse)
def update_shelf(
    shelf_id: int, shelf: ShelfUpdate, current_user: dict = Depends(get_current_user)
):
    """Atualiza prateleira"""
    conn = get_db()
    cur = conn.cursor()

    # Verifica se prateleira existe
    cur.execute("SELECT id, created_by FROM shelves WHERE id = ?", (shelf_id,))
    shelf_data = cur.fetchone()
    if not shelf_data:
        conn.close()
        raise HTTPException(status_code=404, detail="Prateleira não encontrada")

    # Verifica permissão (só criador ou admin pode editar)
    if shelf_data["created_by"] != current_user["id"] and not current_user["isAdmin"]:
        conn.close()
        raise HTTPException(
            status_code=403, detail="Sem permissão para editar esta prateleira"
        )

    # Atualiza prateleira
    update_fields = []
    values = []

    if shelf.name is not None:
        update_fields.append("name = ?")
        values.append(shelf.name)

    if shelf.description is not None:
        update_fields.append("description = ?")
        values.append(shelf.description)

    if update_fields:
        update_fields.append("updated_at = CURRENT_TIMESTAMP")
        values.append(shelf_id)

        query = f"UPDATE shelves SET {', '.join(update_fields)} WHERE id = ?"
        cur.execute(query, values)
        conn.commit()

    # Retorna prateleira atualizada
    cur.execute(
        """
        SELECT s.*, COUNT(si.id) as item_count
        FROM shelves s
        LEFT JOIN shelf_items si ON s.id = si.shelf_id
        WHERE s.id = ?
        GROUP BY s.id
    """,
        (shelf_id,),
    )

    updated_shelf = dict(cur.fetchone())
    conn.close()

    return updated_shelf


@app.delete("/shelves/{shelf_id}")
def delete_shelf(shelf_id: int, current_user: dict = Depends(get_current_user)):
    """Exclui prateleira (apenas admin)"""
    if not current_user["isAdmin"]:
        raise HTTPException(
            status_code=403, detail="Apenas administradores podem excluir prateleiras"
        )

    conn = get_db()
    cur = conn.cursor()

    # Verifica se prateleira existe
    cur.execute("SELECT id FROM shelves WHERE id = ?", (shelf_id,))
    if not cur.fetchone():
        conn.close()
        raise HTTPException(status_code=404, detail="Prateleira não encontrada")

    # Remove prateleira (cascade remove os items)
    cur.execute("DELETE FROM shelves WHERE id = ?", (shelf_id,))
    conn.commit()
    conn.close()

    return {"success": True, "message": "Prateleira excluída com sucesso"}


# ==================== ENDPOINTS DE ITENS ====================


@app.get("/shelves/{shelf_id}/items", response_model=List[ShelfItemResponse])
async def get_shelf_items(
    shelf_id: int,
    include_product_data: bool = True,
    current_user: dict = Depends(get_current_user),
):
    conn = get_db()
    cur = conn.cursor()

    cur.execute(
        """
        SELECT *
        FROM shelf_items
        WHERE shelf_id = ?
        ORDER BY added_at DESC
        """,
        (shelf_id,),
    )

    rows = cur.fetchall()
    conn.close()

    items = []

    for row in rows:
        item = dict(row)

        product_data = None
        if include_product_data:
            product_info = await get_wordpress_product(item["product_id"])
            if product_info:
                product_data = ProductReference(**product_info)

        items.append(
            ShelfItemResponse(
                **item,
                product_data=product_data,
            )
        )

    return items


@app.post("/shelves/{shelf_id}/items", response_model=ShelfItemResponse)
async def add_item_to_shelf(
    shelf_id: int,
    item: ShelfItemAdd,
    current_user: dict = Depends(get_current_user),
):
    conn = get_db()
    cur = conn.cursor()

    # Verifica se prateleira existe
    cur.execute("SELECT id FROM shelves WHERE id = ?", (shelf_id,))
    if not cur.fetchone():
        conn.close()
        raise HTTPException(status_code=404, detail="Prateleira não encontrada")

    # Verifica se produto já está em outra prateleira
    cur.execute(
        "SELECT shelf_id FROM shelf_items WHERE product_id = ?",
        (item.product_id,),
    )
    if cur.fetchone():
        conn.close()
        raise HTTPException(
            status_code=409,
            detail="Produto já está em uma prateleira",
        )

    # 🔥 (Opcional) Validar se produto existe no WP
    product_info = await get_wordpress_product(item.product_id)
    if not product_info:
        conn.close()
        raise HTTPException(status_code=404, detail="Produto não existe no WordPress")

    # Inserir referência na prateleira
    cur.execute(
        """
        INSERT INTO shelf_items (shelf_id, product_id, quantity, added_by)
        VALUES (?, ?, ?, ?)
        """,
        (
            shelf_id,
            item.product_id,
            item.quantity,
            current_user["id"],
        ),
    )

    # Capturar o ID do item recém-inserido (deve ser antes do histórico)
    item_id = cur.lastrowid

    # Inserir no histórico (entrada)
    cur.execute(
        """
        INSERT INTO item_history (product_id, shelf_id, entrada)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        """,
        (item.product_id, shelf_id),
    )

    conn.commit()

    # Buscar o item completo para retornar
    cur.execute("SELECT * FROM shelf_items WHERE id = ?", (item_id,))
    item_data = dict(cur.fetchone())
    conn.close()

    return ShelfItemResponse(**item_data)


@app.delete("/shelves/{shelf_id}/items/{product_id}")
def remove_item_from_shelf(
    shelf_id: int, product_id: int, current_user: dict = Depends(get_current_user)
):
    """Remove item da prateleira"""
    conn = get_db()
    cur = conn.cursor()

    # Remove item
    cur.execute(
        """
        DELETE FROM shelf_items 
        WHERE shelf_id = ? AND product_id = ?
    """,
        (shelf_id, product_id),
    )

    if cur.rowcount == 0:
        conn.close()
        raise HTTPException(
            status_code=404, detail="Item não encontrado nesta prateleira"
        )

    conn.commit()
    conn.close()

    return {"success": True, "message": "Item removido da prateleira"}


@app.post("/items/move", response_model=ShelfItemResponse)
def move_item(move: ShelfItemMove, current_user: dict = Depends(get_current_user)):
    """Move item entre prateleiras"""
    conn = get_db()
    cur = conn.cursor()

    # Verifica se prateleira destino existe
    cur.execute("SELECT id, name FROM shelves WHERE id = ?", (move.to_shelf_id,))
    to_shelf = cur.fetchone()
    if not to_shelf:
        conn.close()
        raise HTTPException(status_code=404, detail="Prateleira destino não encontrada")

    # Obtém prateleira atual do produto
    cur.execute(
        """
        SELECT si.id, si.shelf_id, s.name 
        FROM shelf_items si
        JOIN shelves s ON si.shelf_id = s.id
        WHERE si.product_id = ?
    """,
        (move.product_id,),
    )

    current_item = cur.fetchone()

    if not current_item:
        conn.close()
        raise HTTPException(
            status_code=404, detail="Produto não está em nenhuma prateleira"
        )

    from_shelf_id = current_item["shelf_id"]

    # Verifica se já está na mesma prateleira
    if from_shelf_id == move.to_shelf_id:
        conn.close()
        raise HTTPException(status_code=400, detail="Produto já está nesta prateleira")

    # 🔥 REGRA DE NEGÓCIO: Atualiza prateleira do produto
    cur.execute(
        """
        UPDATE shelf_items 
        SET shelf_id = ?, updated_at = CURRENT_TIMESTAMP
        WHERE product_id = ?
    """,
        (move.to_shelf_id, move.product_id),
    )

    # Atualizar saída do histórico anterior (onde saida é nulo)
    cur.execute(
        """
        UPDATE item_history
        SET saida = CURRENT_TIMESTAMP
        WHERE product_id = ? AND shelf_id = ? AND saida IS NULL
        """,
        (move.product_id, from_shelf_id),
    )

    # Inserir novo histórico para a prateleira destino
    cur.execute(
        """
        INSERT INTO item_history (product_id, shelf_id, entrada)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        """,
        (move.product_id, move.to_shelf_id),
    )

    conn.commit()

    # Retorna item atualizado
    cur.execute("SELECT * FROM shelf_items WHERE product_id = ?", (move.product_id,))
    item_data = dict(cur.fetchone())
    conn.close()

    return ShelfItemResponse(
        id=item_data["id"],
        shelf_id=item_data["shelf_id"],
        product_id=item_data["product_id"],
        quantity=item_data["quantity"],
        added_at=item_data["added_at"],
    )


# ==================== ENDPOINTS DE PRODUTOS ====================


@app.get("/products/search")
async def search_products(
    request: Request,
    q: str = Query(..., description="Termo de busca"),
    type: str = Query("name", description="Tipo de busca: 'name' ou 'sku'"),
    limit: int = Query(20, description="Limite de resultados"),
):
    """Busca produtos no WordPress por nome ou SKU e adiciona info de prateleira"""
    try:
        print(f"Buscando produtos - Termo: {q}, Tipo: {type}, Limite: {limit}")

        # Busca no WordPress
        products = await search_wordpress_products(
            search=q, search_type=type, limit=limit
        )

        # Adicionar informação de prateleira para cada produto
        conn = get_db()
        cur = conn.cursor()
        for product in products:
            product_id = product.get("id")
            cur.execute(
                """
                SELECT si.shelf_id, s.name as shelf_name
                FROM shelf_items si
                JOIN shelves s ON si.shelf_id = s.id
                WHERE si.product_id = ?
                """,
                (product_id,),
            )
            shelf_info = cur.fetchone()
            if shelf_info:
                product["in_shelf"] = shelf_info["shelf_id"]
                product["shelf_name"] = shelf_info["shelf_name"]
            else:
                product["in_shelf"] = None
                product["shelf_name"] = None
        conn.close()

        return {
            "success": True,
            "data": products,
            "count": len(products),
            "search_type": type,
        }

    except Exception as e:
        print(f"Erro na busca de produtos: {e}")
        import traceback

        traceback.print_exc()
        return {"success": False, "error": str(e), "data": []}


@app.get("/products/check/{product_id}")
async def check_product_status(
    product_id: int, current_user: dict = Depends(get_current_user)
):
    """Verifica status de um produto (se existe e onde está)"""
    conn = get_db()
    cur = conn.cursor()

    # Verifica se está em alguma prateleira
    cur.execute(
        """
        SELECT si.shelf_id, s.name as shelf_name
        FROM shelf_items si
        JOIN shelves s ON si.shelf_id = s.id
        WHERE si.product_id = ?
    """,
        (product_id,),
    )

    shelf_info = cur.fetchone()

    # Busca dados do produto no WordPress
    product_data = None
    try:
        product_data = await get_wordpress_product(product_id)
    except:
        pass

    conn.close()

    return {
        "success": True,
        "product_id": product_id,
        "in_shelf": shelf_info["shelf_id"] if shelf_info else None,
        "shelf_name": shelf_info["shelf_name"] if shelf_info else None,
        "product_exists_in_wp": product_data is not None,
        "product_data": product_data,
    }


# ==================== ENDPOINT DE BATCH PARA LOCALIZAÇÃO DE PRODUTOS ====================
# Adicionado para permitir que o frontend consulte a localização de vários produtos de uma só vez,
# otimizando a busca direta no WordPress.

@app.post("/products/batch-status")
async def batch_product_status(product_ids: List[int], current_user: dict = Depends(get_current_user)):
    """Retorna a localização atual de múltiplos produtos"""
    if not product_ids:
        return {}
    
    conn = get_db()
    cur = conn.cursor()
    
    # Cria placeholders dinâmicos (?,?,?...)
    placeholders = ','.join(['?'] * len(product_ids))
    
    cur.execute(f"""
        SELECT product_id, shelf_id, s.name as shelf_name
        FROM shelf_items si
        JOIN shelves s ON si.shelf_id = s.id
        WHERE product_id IN ({placeholders})
    """, product_ids)
    
    rows = cur.fetchall()
    conn.close()
    
    result = {}
    for row in rows:
        result[row["product_id"]] = {
            "shelf_id": row["shelf_id"],
            "shelf_name": row["shelf_name"]
        }
    
    return result


# ==================== ENDPOINT DE HISTÓRICO ====================


@app.get("/items/{product_id}/history")
def get_item_history(product_id: int, current_user: dict = Depends(get_current_user)):
    conn = get_db()
    cur = conn.cursor()

    cur.execute("""
        SELECT 
            ih.id,
            ih.product_id,
            ih.shelf_id,
            s.name as shelf_name,
            ih.entrada,
            ih.saida
        FROM item_history ih
        JOIN shelves s ON ih.shelf_id = s.id
        WHERE ih.product_id = ?
        ORDER BY ih.entrada DESC
    """, (product_id,))

    rows = cur.fetchall()
    conn.close()

    # Converter para lista de dicionários
    history = []
    for row in rows:
        history.append({
            "id": row["id"],
            "product_id": row["product_id"],
            "shelf_id": row["shelf_id"],
            "shelf_name": row["shelf_name"],
            "entrada": row["entrada"],
            "saida": row["saida"]
        })

    return history


# ==================== ENDPOINTS DE DASHBOARD ====================


@app.get("/dashboard/stats")
def get_dashboard_stats(current_user: dict = Depends(get_current_user)):
    """Estatísticas do sistema"""
    conn = get_db()
    cur = conn.cursor()

    # Estatísticas básicas
    cur.execute("SELECT COUNT(*) as total FROM shelves")
    total_shelves = cur.fetchone()["total"]

    cur.execute("SELECT COUNT(*) as total FROM shelf_items")
    total_items = cur.fetchone()["total"]

    cur.execute("""
        SELECT COUNT(DISTINCT shelf_id) as total 
        FROM shelves s
        WHERE EXISTS (SELECT 1 FROM shelf_items si WHERE si.shelf_id = s.id)
    """)
    used_shelves = cur.fetchone()["total"]

    cur.execute("""
        SELECT COUNT(*) as total 
        FROM shelves s
        WHERE NOT EXISTS (SELECT 1 FROM shelf_items si WHERE si.shelf_id = s.id)
    """)
    empty_shelves = cur.fetchone()["total"]

    conn.close()

    return {
        "success": True,
        "stats": {
            "total_shelves": total_shelves,
            "total_items": total_items,
            "used_shelves": used_shelves,
            "empty_shelves": empty_shelves,
            "avg_items_per_shelf": round(total_items / max(used_shelves, 1), 1),
        },
    }


# ==================== ENDPOINTS DE SAÚDE ====================


@app.get("/")
def root():
    """Página inicial"""
    return {
        "service": "Shelf Manager API",
        "version": "1.0.0",
        "status": "online",
        "endpoints": {
            "auth": ["POST /auth/login", "POST /auth/register"],
            "shelves": [
                "GET /shelves",
                "POST /shelves",
                "PUT /shelves/{id}",
                "DELETE /shelves/{id}",
            ],
            "items": [
                "GET /shelves/{id}/items",
                "POST /shelves/{id}/items",
                "DELETE /shelves/{id}/items/{pid}",
                "POST /items/move",
            ],
            "products": ["GET /products/search", "GET /products/check/{id}", "POST /products/batch-status"],
            "dashboard": ["GET /dashboard/stats"],
            "history": ["GET /items/{product_id}/history"],
        },
    }


@app.get("/health")
def health_check():
    """Verifica saúde da API"""
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("SELECT 1")
        conn.close()

        return {
            "status": "healthy",
            "database": "connected",
            "timestamp": datetime.now().isoformat(),
        }
    except Exception as e:
        return {
            "status": "unhealthy",
            "error": str(e),
            "timestamp": datetime.now().isoformat(),
        }


# ==================== INICIALIZAÇÃO ====================


@app.on_event("startup")
def startup_event():
    """Inicializa banco na startup"""
    init_db()
    create_admin()
    print("✅ Banco de dados inicializado")
    print("👑 Admin criado: usuario=admin, senha=123456")
    print("🌐 API rodando em: http://localhost:8000")
    print("📚 Documentação: http://localhost:8000/docs")


# ==================== CONEXÕES ====================


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")  # Ativa foreign keys
    return conn


async def get_wordpress_product(product_id: int) -> Optional[WordpressProduct]:
    """Busca dados do produto no WordPress"""
    async with httpx.AsyncClient(verify=False) as client:
        try:
            response = await client.get(
                f"{WORDPRESS_API_URL}/by-id/{product_id}",
                headers={"X-API-Key": WORDPRESS_API_KEY},
                timeout=4.0,
            )

            if response.status_code == 200:
                data = response.json()
                if data.get("success"):
                    return data.get("data")
        except Exception as e:
            print(f"Erro ao buscar produto {product_id}: {e}")

    return None


async def search_wordpress_products(
    search: str, search_type: str = "name", limit: int = 20
) -> List[dict]:
    """Busca produtos no WordPress
    Args:
        search: termo de busca
        search_type: "name" para busca por nome, "sku" para busca por SKU
        limit: limite de resultados
    """
    async with httpx.AsyncClient(verify=False) as client:
        try:
            # Define o endpoint baseado no tipo de busca
            if search_type == "sku":
                endpoint = f"{WORDPRESS_API_URL}/sku-search"
                params = {"sku": search}
            else:  # name
                endpoint = f"{WORDPRESS_API_URL}/search"
                params = {"q": search, "limit": limit}

            print(f"Buscando no WordPress - Endpoint: {endpoint}, Params: {params}")

            response = await client.get(
                endpoint,
                params=params,
                headers={"X-API-Key": WORDPRESS_API_KEY},
                timeout=4.0,
            )

            if response.status_code == 200:
                data = response.json()
                print(f"Resposta do WordPress: {data}")

                if data.get("success"):
                    return data.get("data", [])
            else:
                print(f"Erro na resposta: {response.status_code} - {response.text}")

        except Exception as e:
            print(f"Erro na busca de produtos: {e}")
            import traceback

            traceback.print_exc()

    return []