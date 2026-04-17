import json
import tkinter as tk
from tkinter import ttk, messagebox
from pathlib import Path
import requests

APP_TITLE = "Supabase REST API Desktop Client"
SETTINGS_FILE = Path.home() / ".supabase_rest_client_settings.json"


class ApiClient:
    def __init__(self, base_url_getter):
        self._base_url_getter = base_url_getter

    @property
    def base_url(self) -> str:
        return self._base_url_getter().strip().rstrip("/")

    def request(self, method: str, path: str, **kwargs):
        url = f"{self.base_url}{path}"
        return requests.request(method, url, timeout=20, **kwargs)


class App(ttk.Frame):
    def __init__(self, master):
        super().__init__(master, padding=16)
        self.master = master
        self.client = ApiClient(lambda: self.base_url_var.get())

        self.base_url_var = tk.StringVar(value="http://localhost:8000")
        self.status_var = tk.StringVar(value="Ready.")
        self.filter_email_var = tk.StringVar()

        self.signup_vars = {
            "name": tk.StringVar(),
            "email": tk.StringVar(),
            "password": tk.StringVar(),
        }
        self.login_vars = {
            "email": tk.StringVar(),
            "password": tk.StringVar(),
        }
        self.update_vars = {
            "email": tk.StringVar(),
            "new-email": tk.StringVar(),
            "new-name": tk.StringVar(),
            "new-password": tk.StringVar(),
        }
        self.delete_email_var = tk.StringVar()

        self.pack(fill="both", expand=True)
        self._build_style()
        self._build_ui()
        self._load_settings()

    def _build_style(self):
        style = ttk.Style(self.master)
        try:
            style.theme_use("clam")
        except tk.TclError:
            pass

        bg = "#f5f7fb"
        card = "#ffffff"
        text = "#111827"
        muted = "#6b7280"
        border = "#dbe2ea"

        self.colors = {
            "bg": bg,
            "card": card,
            "text": text,
            "muted": muted,
            "border": border,
            "console_bg": "#0f172a",
            "console_fg": "#e5e7eb",
        }

        self.master.configure(bg=bg)

        style.configure(".", font=("Segoe UI", 10))
        style.configure("Root.TFrame", background=bg)
        style.configure("TLabel", background=card, foreground=text)
        style.configure("Title.TLabel", background=bg, foreground=text, font=("Segoe UI", 20, "bold"))
        style.configure("Sub.TLabel", background=bg, foreground=muted, font=("Segoe UI", 10))
        style.configure("CardText.TLabel", background=card, foreground=muted, font=("Segoe UI", 10))
        style.configure("TEntry", padding=8)
        style.configure("TNotebook", background=card, borderwidth=0)
        style.configure("TNotebook.Tab", padding=(14, 8), font=("Segoe UI", 10, "bold"))

        style.configure("Primary.TButton", padding=(14, 9), font=("Segoe UI Semibold", 10))
        style.configure("Secondary.TButton", padding=(12, 8), font=("Segoe UI", 10))

        style.configure(
            "Treeview",
            background="white",
            foreground="black",
            fieldbackground="white",
            rowheight=30,
            font=("Segoe UI", 10),
        )
        style.configure("Treeview.Heading", font=("Segoe UI Semibold", 10), padding=(8, 8))
        style.map(
            "Treeview",
            background=[("selected", "#2563eb")],
            foreground=[("selected", "white")],
        )

    def _build_ui(self):
        self.configure(style="Root.TFrame")
        self.columnconfigure(0, weight=1)
        self.rowconfigure(2, weight=1)

        header = ttk.Frame(self, style="Root.TFrame")
        header.grid(row=0, column=0, sticky="ew", pady=(0, 14))
        header.columnconfigure(0, weight=1)

        ttk.Label(header, text=APP_TITLE, style="Title.TLabel").grid(row=0, column=0, sticky="w")
        ttk.Label(
            header,
            text="A modern desktop client for your PHP + Supabase REST API.",
            style="Sub.TLabel",
        ).grid(row=1, column=0, sticky="w", pady=(4, 0))

        top = ttk.Frame(self, style="Root.TFrame")
        top.grid(row=1, column=0, sticky="ew", pady=(0, 14))
        top.columnconfigure(0, weight=3)
        top.columnconfigure(1, weight=2)

        self._build_connection_card(top)
        self._build_quick_help_card(top)

        main = ttk.Panedwindow(self, orient="horizontal")
        main.grid(row=2, column=0, sticky="nsew")

        left = ttk.Frame(main, style="Root.TFrame")
        right = ttk.Frame(main, style="Root.TFrame")
        left.columnconfigure(0, weight=1)
        left.rowconfigure(0, weight=1)
        right.columnconfigure(0, weight=1)
        right.rowconfigure(1, weight=1)

        main.add(left, weight=6)
        main.add(right, weight=5)

        self._build_tabs_card(left)
        self._build_users_card(right)
        self._build_response_card(right)

        status = tk.Label(
            self,
            textvariable=self.status_var,
            anchor="w",
            bg="#e8eefc",
            fg="#1f2937",
            padx=12,
            pady=8,
            relief="flat",
        )
        status.grid(row=3, column=0, sticky="ew", pady=(14, 0))

    def _build_connection_card(self, parent):
        card = tk.Frame(parent, bg=self.colors["card"], highlightthickness=1, highlightbackground=self.colors["border"])
        card.grid(row=0, column=0, sticky="ew", padx=(0, 8))
        card.grid_columnconfigure(1, weight=1)

        tk.Label(card, text="Connection", bg=self.colors["card"], fg=self.colors["text"], font=("Segoe UI", 11, "bold")).grid(
            row=0, column=0, columnspan=4, sticky="w", padx=14, pady=(12, 6)
        )

        tk.Label(card, text="Base URL", bg=self.colors["card"], fg=self.colors["text"]).grid(
            row=1, column=0, sticky="w", padx=(14, 8), pady=(6, 14)
        )

        ttk.Entry(card, textvariable=self.base_url_var).grid(
            row=1, column=1, sticky="ew", padx=(0, 8), pady=(6, 14)
        )

        ttk.Button(card, text="Health Check", style="Primary.TButton", command=self.health_check).grid(
            row=1, column=2, padx=(0, 8), pady=(6, 14)
        )

        ttk.Button(card, text="Save", style="Secondary.TButton", command=self._save_settings).grid(
            row=1, column=3, padx=(0, 14), pady=(6, 14)
        )

    def _build_quick_help_card(self, parent):
        card = tk.Frame(parent, bg=self.colors["card"], highlightthickness=1, highlightbackground=self.colors["border"])
        card.grid(row=0, column=1, sticky="nsew", padx=(8, 0))

        tk.Label(card, text="Quick Tips", bg=self.colors["card"], fg=self.colors["text"], font=("Segoe UI", 11, "bold")).pack(
            anchor="w", padx=14, pady=(12, 6)
        )

        tips = [
            "Run your PHP server first.",
            "Base URL should usually be http://localhost:8000",
            "Use the Users panel to select and prefill Update/Delete.",
        ]
        for tip in tips:
            tk.Label(card, text=f"• {tip}", bg=self.colors["card"], fg=self.colors["muted"], anchor="w", justify="left").pack(
                fill="x", padx=14, pady=2
            )
        tk.Label(card, text="", bg=self.colors["card"]).pack(pady=(0, 6))

    def _build_tabs_card(self, parent):
        card = tk.Frame(parent, bg=self.colors["card"], highlightthickness=1, highlightbackground=self.colors["border"])
        card.grid(row=0, column=0, sticky="nsew")
        card.grid_rowconfigure(1, weight=1)
        card.grid_columnconfigure(0, weight=1)

        tk.Label(card, text="Actions", bg=self.colors["card"], fg=self.colors["text"], font=("Segoe UI", 11, "bold")).grid(
            row=0, column=0, sticky="w", padx=14, pady=(12, 8)
        )

        notebook = ttk.Notebook(card)
        notebook.grid(row=1, column=0, sticky="nsew", padx=12, pady=(0, 12))

        signup_tab = ttk.Frame(notebook, padding=16)
        login_tab = ttk.Frame(notebook, padding=16)
        update_tab = ttk.Frame(notebook, padding=16)
        delete_tab = ttk.Frame(notebook, padding=16)

        for tab in (signup_tab, login_tab, update_tab, delete_tab):
            tab.columnconfigure(1, weight=1)

        notebook.add(signup_tab, text="Signup")
        notebook.add(login_tab, text="Login")
        notebook.add(update_tab, text="Update")
        notebook.add(delete_tab, text="Delete")

        self._build_signup_tab(signup_tab)
        self._build_login_tab(login_tab)
        self._build_update_tab(update_tab)
        self._build_delete_tab(delete_tab)

    def _build_signup_tab(self, tab):
        ttk.Label(tab, text="Create a new user in user_demo.", style="CardText.TLabel").grid(
            row=0, column=0, columnspan=2, sticky="w", pady=(0, 12)
        )
        self._add_field(tab, "Name", self.signup_vars["name"], 1)
        self._add_field(tab, "Email", self.signup_vars["email"], 2)
        self._add_field(tab, "Password", self.signup_vars["password"], 3, show="*")

        actions = ttk.Frame(tab)
        actions.grid(row=4, column=0, columnspan=2, sticky="w", pady=(16, 0))
        ttk.Button(actions, text="Create User", style="Primary.TButton", command=self.create_user).pack(side="left")
        ttk.Button(actions, text="Clear", style="Secondary.TButton", command=lambda: self._clear_vars(self.signup_vars)).pack(side="left", padx=8)

    def _build_login_tab(self, tab):
        ttk.Label(tab, text="Authenticate against your PHP API.", style="CardText.TLabel").grid(
            row=0, column=0, columnspan=2, sticky="w", pady=(0, 12)
        )
        self._add_field(tab, "Email", self.login_vars["email"], 1)
        self._add_field(tab, "Password", self.login_vars["password"], 2, show="*")

        actions = ttk.Frame(tab)
        actions.grid(row=3, column=0, columnspan=2, sticky="w", pady=(16, 0))
        ttk.Button(actions, text="Login", style="Primary.TButton", command=self.login_user).pack(side="left")
        ttk.Button(actions, text="Clear", style="Secondary.TButton", command=lambda: self._clear_vars(self.login_vars)).pack(side="left", padx=8)

    def _build_update_tab(self, tab):
        ttk.Label(tab, text="Update a user by current email.", style="CardText.TLabel").grid(
            row=0, column=0, columnspan=2, sticky="w", pady=(0, 12)
        )
        self._add_field(tab, "Current email", self.update_vars["email"], 1)
        self._add_field(tab, "New email", self.update_vars["new-email"], 2)
        self._add_field(tab, "New name", self.update_vars["new-name"], 3)
        self._add_field(tab, "New password", self.update_vars["new-password"], 4, show="*")

        actions = ttk.Frame(tab)
        actions.grid(row=5, column=0, columnspan=2, sticky="w", pady=(16, 0))
        ttk.Button(actions, text="Update User", style="Primary.TButton", command=self.update_user).pack(side="left")
        ttk.Button(actions, text="Clear", style="Secondary.TButton", command=lambda: self._clear_vars(self.update_vars)).pack(side="left", padx=8)

    def _build_delete_tab(self, tab):
        ttk.Label(tab, text="Delete a user by email.", style="CardText.TLabel").grid(
            row=0, column=0, columnspan=2, sticky="w", pady=(0, 12)
        )
        self._add_field(tab, "Email", self.delete_email_var, 1)

        actions = ttk.Frame(tab)
        actions.grid(row=2, column=0, columnspan=2, sticky="w", pady=(16, 0))
        ttk.Button(actions, text="Delete User", style="Primary.TButton", command=self.delete_user).pack(side="left")

    def _build_users_card(self, parent):
        card = tk.Frame(parent, bg=self.colors["card"], highlightthickness=1, highlightbackground=self.colors["border"])
        card.grid(row=0, column=0, sticky="nsew", pady=(0, 12))
        card.grid_rowconfigure(2, weight=1)
        card.grid_columnconfigure(0, weight=1)

        tk.Label(card, text="Users", bg=self.colors["card"], fg=self.colors["text"], font=("Segoe UI", 11, "bold")).grid(
            row=0, column=0, sticky="w", padx=14, pady=(12, 8)
        )

        controls = tk.Frame(card, bg=self.colors["card"])
        controls.grid(row=1, column=0, sticky="ew", padx=14, pady=(0, 10))
        controls.grid_columnconfigure(1, weight=1)

        tk.Label(controls, text="Email filter", bg=self.colors["card"], fg=self.colors["text"]).grid(row=0, column=0, sticky="w", padx=(0, 8))
        ttk.Entry(controls, textvariable=self.filter_email_var).grid(row=0, column=1, sticky="ew", padx=(0, 8))
        ttk.Button(controls, text="Load Users", style="Primary.TButton", command=self.load_users).grid(row=0, column=2, padx=(0, 8))
        ttk.Button(controls, text="Clear", style="Secondary.TButton", command=self.clear_user_filter).grid(row=0, column=3)

        table_wrap = tk.Frame(card, bg=self.colors["card"])
        table_wrap.grid(row=2, column=0, sticky="nsew", padx=14, pady=(0, 14))
        table_wrap.grid_rowconfigure(0, weight=1)
        table_wrap.grid_columnconfigure(0, weight=1)

        columns = ("user_id", "name", "email", "password")
        self.tree = ttk.Treeview(table_wrap, columns=columns, show="headings", selectmode="browse")

        widths = {"user_id": 80, "name": 150, "email": 220, "password": 180}
        for col in columns:
            self.tree.heading(col, text=col)
            self.tree.column(col, width=widths[col], anchor="w")

        self.tree.tag_configure("normal", background="white", foreground="black")

        y_scroll = ttk.Scrollbar(table_wrap, orient="vertical", command=self.tree.yview)
        x_scroll = ttk.Scrollbar(table_wrap, orient="horizontal", command=self.tree.xview)
        self.tree.configure(yscrollcommand=y_scroll.set, xscrollcommand=x_scroll.set)

        self.tree.grid(row=0, column=0, sticky="nsew")
        y_scroll.grid(row=0, column=1, sticky="ns")
        x_scroll.grid(row=1, column=0, sticky="ew")

        self.tree.bind("<<TreeviewSelect>>", self._on_row_selected)

    def _build_response_card(self, parent):
        card = tk.Frame(parent, bg=self.colors["card"], highlightthickness=1, highlightbackground=self.colors["border"])
        card.grid(row=1, column=0, sticky="nsew")
        card.grid_rowconfigure(1, weight=1)
        card.grid_columnconfigure(0, weight=1)

        tk.Label(card, text="API Response", bg=self.colors["card"], fg=self.colors["text"], font=("Segoe UI", 11, "bold")).grid(
            row=0, column=0, sticky="w", padx=14, pady=(12, 8)
        )

        wrap = tk.Frame(card, bg=self.colors["card"])
        wrap.grid(row=1, column=0, sticky="nsew", padx=14, pady=(0, 14))
        wrap.grid_rowconfigure(0, weight=1)
        wrap.grid_columnconfigure(0, weight=1)

        self.response = tk.Text(
            wrap,
            wrap="word",
            font=("Consolas", 10),
            bg=self.colors["console_bg"],
            fg=self.colors["console_fg"],
            insertbackground=self.colors["console_fg"],
            relief="flat",
            padx=14,
            pady=14,
        )
        self.response.grid(row=0, column=0, sticky="nsew")

        y_scroll = ttk.Scrollbar(wrap, orient="vertical", command=self.response.yview)
        self.response.configure(yscrollcommand=y_scroll.set)
        y_scroll.grid(row=0, column=1, sticky="ns")

        self._write_response(
            "Ready.\n\n"
            "Available endpoints:\n"
            "GET /\nPOST /signup\nPOST /login\nGET /users\nPUT /update\nDELETE /delete"
        )

    def _add_field(self, parent, label, variable, row, show=None):
        ttk.Label(parent, text=label).grid(row=row, column=0, sticky="w", pady=7, padx=(0, 10))
        ttk.Entry(parent, textvariable=variable, show=show).grid(row=row, column=1, sticky="ew", pady=7)

    def _write_response(self, text):
        self.response.delete("1.0", "end")
        self.response.insert("1.0", text)

    def _format_response(self, response, label):
        body = response.text.strip()
        try:
            body = json.dumps(response.json(), indent=2)
        except Exception:
            if not body:
                body = "No response body returned."
        return f"Request: {label}\nHTTP Status: {response.status_code} {response.reason}\n\n{body}"

    def _send(self, method, path, label, **kwargs):
        try:
            response = self.client.request(method, path, **kwargs)
            self._write_response(self._format_response(response, label))
            self.status_var.set(f"{label} → HTTP {response.status_code}")
            return response
        except requests.RequestException as exc:
            self._write_response(f"Request: {label}\nHTTP Status: -1\n\n{exc}")
            self.status_var.set(f"{label} failed")
            return None

    def _save_settings(self):
        data = {"base_url": self.base_url_var.get().strip()}
        SETTINGS_FILE.write_text(json.dumps(data, indent=2), encoding="utf-8")
        self.status_var.set(f"Saved base URL to {SETTINGS_FILE}")

    def _load_settings(self):
        try:
            if SETTINGS_FILE.exists():
                data = json.loads(SETTINGS_FILE.read_text(encoding="utf-8"))
                self.base_url_var.set(data.get("base_url", self.base_url_var.get()))
        except Exception:
            pass

    def _clear_vars(self, mapping):
        for var in mapping.values():
            var.set("")

    def health_check(self):
        self._send("GET", "/", "GET /")

    def create_user(self):
        payload = {k: v.get().strip() for k, v in self.signup_vars.items()}
        if not all(payload.values()):
            messagebox.showwarning("Missing fields", "Fill in name, email, and password.")
            return
        response = self._send("POST", "/signup", "POST /signup", json=payload)
        if response is not None and response.ok:
            self._clear_vars(self.signup_vars)
            self.load_users()

    def login_user(self):
        payload = {k: v.get().strip() for k, v in self.login_vars.items()}
        if not all(payload.values()):
            messagebox.showwarning("Missing fields", "Fill in email and password.")
            return
        self._send("POST", "/login", "POST /login", json=payload)

    def load_users(self):
        email = self.filter_email_var.get().strip()
        params = {"email": email} if email else None

        response = self._send("GET", "/users", "GET /users", params=params)
        if response is None or not response.ok:
            return

        try:
            payload = response.json()
        except Exception as exc:
            self._write_response(f"GET /users returned invalid JSON:\n\n{exc}\n\nRaw body:\n{response.text}")
            return

        if isinstance(payload, list):
            rows = payload
        elif isinstance(payload, dict):
            if isinstance(payload.get("data"), list):
                rows = payload["data"]
            elif isinstance(payload.get("users"), list):
                rows = payload["users"]
            elif isinstance(payload.get("result"), list):
                rows = payload["result"]
            else:
                rows = []
        else:
            rows = []

        self._refresh_tree(rows)
        self.status_var.set(f"Loaded {len(rows)} user(s).")

    def clear_user_filter(self):
        self.filter_email_var.set("")
        self.load_users()

    def update_user(self):
        payload = {k: v.get().strip() for k, v in self.update_vars.items()}
        if not payload["email"]:
            messagebox.showwarning("Missing email", "Enter the current email.")
            return
        if not any(payload[key] for key in ("new-email", "new-name", "new-password")):
            messagebox.showwarning("Missing changes", "Enter at least one new value.")
            return
        response = self._send("PUT", "/update", "PUT /update", json=payload)
        if response is not None and response.ok:
            self.load_users()

    def delete_user(self):
        email = self.delete_email_var.get().strip()
        if not email:
            messagebox.showwarning("Missing email", "Enter an email to delete.")
            return
        if not messagebox.askyesno("Confirm delete", f"Delete user with email:\n{email}"):
            return
        response = self._send("DELETE", "/delete", "DELETE /delete", params={"email": email})
        if response is not None and response.ok:
            self.delete_email_var.set("")
            self.load_users()

    def _refresh_tree(self, rows):
        for item in self.tree.get_children():
            self.tree.delete(item)

        for row in rows:
            if not isinstance(row, dict):
                continue

            self.tree.insert(
                "",
                "end",
                values=(
                    row.get("user_id", ""),
                    row.get("name", ""),
                    row.get("email", ""),
                    row.get("password", ""),
                ),
                tags=("normal",),
            )

        self.tree.update_idletasks()

    def _on_row_selected(self, _event=None):
        selection = self.tree.selection()
        if not selection:
            return

        values = self.tree.item(selection[0], "values")
        if len(values) < 4:
            return

        _, name, email, password = values
        self.update_vars["email"].set(email)
        self.update_vars["new-email"].set(email)
        self.update_vars["new-name"].set(name)
        self.update_vars["new-password"].set(password)
        self.delete_email_var.set(email)


def main():
    root = tk.Tk()
    root.title(APP_TITLE)
    root.geometry("1320x820")
    root.minsize(1120, 700)
    App(root)
    root.mainloop()


if __name__ == "__main__":
    main()
