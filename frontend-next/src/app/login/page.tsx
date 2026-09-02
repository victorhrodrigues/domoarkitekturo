"use client";

import { useState, type FormEvent } from "react";

type LoginResponse = {
  user: {
    id: number;
    name: string;
    email: string;
  };
  token: string;
};

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [usuario, setUsuario] = useState<unknown>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setErro(null);
    setUsuario(null);

    try {
      const loginResponse = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      if (!loginResponse.ok) {
        throw new Error("Credenciais inválidas.");
      }

      const { token }: LoginResponse = await loginResponse.json();

      const userResponse = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/user`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      });

      if (!userResponse.ok) {
        throw new Error("Login funcionou, mas /api/user falhou.");
      }

      setUsuario(await userResponse.json());
    } catch (err) {
      setErro(err instanceof Error ? err.message : "Erro desconhecido.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-6 px-6 py-16">
      <form
        onSubmit={handleSubmit}
        className="flex w-full max-w-sm flex-col gap-4 rounded-lg border border-zinc-200 p-6 dark:border-zinc-800"
      >
        <h1 className="text-xl font-semibold">Login (teste de comunicação)</h1>

        <input
          type="email"
          placeholder="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="rounded border border-zinc-300 px-3 py-2 dark:border-zinc-700"
          required
        />

        <input
          type="password"
          placeholder="senha"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="rounded border border-zinc-300 px-3 py-2 dark:border-zinc-700"
          required
        />

        <button
          type="submit"
          disabled={loading}
          className="rounded bg-black px-4 py-2 text-white disabled:opacity-50 dark:bg-white dark:text-black"
        >
          {loading ? "Entrando..." : "Entrar"}
        </button>
      </form>

      {erro && <p className="text-red-600">{erro}</p>}

      {usuario !== null && (
        <pre className="max-w-sm overflow-auto rounded bg-zinc-100 p-4 text-sm dark:bg-zinc-900">
          {JSON.stringify(usuario, null, 2)}
        </pre>
      )}
    </div>
  );
}
