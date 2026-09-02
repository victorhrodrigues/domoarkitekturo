import { apiFetch } from "@/lib/api";
import type { LoginResponse, User } from "@/types/user";

export function login(email: string, password: string) {
  return apiFetch<LoginResponse>("/api/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export function getMe(token: string) {
  return apiFetch<User>("/api/user", {
    headers: { Authorization: `Bearer ${token}` },
  });
}

export function logout(token: string) {
  return apiFetch<null>("/api/logout", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
  });
}
