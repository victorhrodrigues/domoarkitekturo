import HomeAnimation from "@/components/HomeAnimation";

export default function Home() {
  return (
    <div className="h-screen w-full">
      <HomeAnimation>
        <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
          <h1 className="text-3xl font-semibold tracking-tight">Domoarkitekturo</h1>
          <p className="max-w-md text-zinc-600 dark:text-zinc-400">
            Site institucional em construção.
          </p>
        </div>
      </HomeAnimation>
    </div>
  );
}
