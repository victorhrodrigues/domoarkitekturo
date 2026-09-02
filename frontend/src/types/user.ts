export type User = {
  id: number;
  name: string;
  email: string;
};

export type LoginResponse = {
  user: User;
  token: string;
};
