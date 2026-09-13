"use client";

import { Suspense, useMemo, useRef, useState } from "react";
import * as THREE from "three";
import { Canvas, useFrame } from "@react-three/fiber";
import { OrbitControls, ScrollControls, useGLTF, useScroll, useTexture } from "@react-three/drei";
import { Loader } from "./Loader";
// ---------------------------------------------------------------- ajustes
const ARQUIVO_GLB = "/models/casa_corrigida.glb";
const ARQUIVO_LOGO = "/images/logo-domo-otimizado.png";
const COR_LINHA = "#3a2a1a";
const COR_LINHA_VIDRO = "#3d5a73";
const COR_FACE = "#f7f1e4";
const ANGULO_ARESTA = 12;
const FATOR_EXPLOSAO = 1;
const ESCALA_LOGO = 3.5;
const FRACAO_CASA = 0.6;
const FRACAO_LOGO = 0.8;
const DURACAO_PECA_FRACAO = 0.18;
const CAMERA_POS = new THREE.Vector3(22, 16, -24);
const CAMERA_ALVO = new THREE.Vector3(0, 3.5, 0);

const suavizar = (t: number) => 1 - Math.pow(1 - t, 3);

type Peca = { obj: THREE.Object3D; origem: THREE.Vector3; destino: THREE.Vector3; inicio: number };

function Cena({ onFimChange }: { onFimChange: (chegouAoFim: boolean) => void }) {
  const scroll = useScroll();
  const { scene } = useGLTF(ARQUIVO_GLB);
  const texturaLogo = useTexture(ARQUIVO_LOGO);

  const logoRef = useRef<THREE.Sprite>(null);
  const controlesRef = useRef<any>(null);
  const fimAtual = useRef(false); // evita chamar onFimChange toda hora, só quando muda

  const { casa, pecas } = useMemo(() => {
    const clone = scene.clone(true);

    const matFace = new THREE.MeshBasicMaterial({
      color: COR_FACE, polygonOffset: true, polygonOffsetFactor: 1, polygonOffsetUnits: 1,
    });
    const matVidro = matFace.clone();
    matVidro.transparent = true;
    matVidro.opacity = 0.35;
    const matLinha = new THREE.LineBasicMaterial({ color: COR_LINHA });
    const matLinhaVidro = new THREE.LineBasicMaterial({ color: COR_LINHA_VIDRO, transparent: true, opacity: 0.5 });

    clone.traverse((no) => {
      if (!(no instanceof THREE.Mesh)) return;
      const ehVidro = (no.material?.name || "").toLowerCase().includes("vidro");
      no.material = ehVidro ? matVidro : matFace;
      const arestas = new THREE.LineSegments(
        new THREE.EdgesGeometry(no.geometry, ANGULO_ARESTA),
        ehVidro ? matLinhaVidro : matLinha,
      );
      no.add(arestas);
    });

    const lista: Peca[] = [];
    clone.traverse((no) => {
      const v = no.userData.explode_vec as number[] | undefined;
      if (!v) return;
      const vetor = new THREE.Vector3(v[0], v[2], -v[1]).multiplyScalar(FATOR_EXPLOSAO);
      lista.push({
        obj: no,
        destino: no.position.clone(),
        origem: no.position.clone().add(vetor),
        inicio: (no.userData.explode_ordem as number) ?? 0,
      });
    });

    const ordemMax = Math.max(...lista.map((p) => p.inicio)) || 1;
    for (const p of lista) {
      const bruto = (ordemMax - p.inicio) / ordemMax;
      p.inicio = bruto * (1 - DURACAO_PECA_FRACAO);
    }

    return { casa: clone, pecas: lista };
  }, [scene]);

  const logoOrigem = useMemo(() => {
    const frente = CAMERA_ALVO.clone().sub(CAMERA_POS).normalize();
    return CAMERA_POS.clone().addScaledVector(frente, -8);
  }, []);
  const logoDestino = useMemo(() => {
    const frente = CAMERA_ALVO.clone().sub(CAMERA_POS).normalize();
    return CAMERA_POS.clone().addScaledVector(frente, 14);
  }, []);

  useFrame(() => {
    const offset = scroll.offset;

    const pCasa = THREE.MathUtils.clamp(offset / FRACAO_CASA, 0, 1);
    for (const p of pecas) {
      const k = THREE.MathUtils.clamp((pCasa - p.inicio) / DURACAO_PECA_FRACAO, 0, 1);
      p.obj.position.lerpVectors(p.origem, p.destino, suavizar(k));
    }

    const pLogo = THREE.MathUtils.clamp((offset - FRACAO_CASA) / (FRACAO_LOGO - FRACAO_CASA), 0, 1);
    if (logoRef.current) {
      logoRef.current.visible = pLogo > 0;
      logoRef.current.position.lerpVectors(logoOrigem, logoDestino, suavizar(pLogo));
    }
    if (controlesRef.current) controlesRef.current.enabled = offset >= FRACAO_LOGO;

    const chegouAoFim = offset >= 0.999;
    if (chegouAoFim !== fimAtual.current) {
      fimAtual.current = chegouAoFim;
      onFimChange(chegouAoFim);
    }
  });

  return (
    <>
      <primitive object={casa} />
      <sprite ref={logoRef} visible={false} scale={[ESCALA_LOGO, ESCALA_LOGO * (314 / 512), 1]}>
        <spriteMaterial map={texturaLogo} transparent />
      </sprite>
      <OrbitControls ref={controlesRef} enabled={false} enableZoom={false} target={CAMERA_ALVO} enableDamping  />
    </>
  );
}

export default function HomeAnimation({ children }: { children?: React.ReactNode }) {
  const [concluido, setConcluido] = useState(false);

  return (
    <div className="relative h-screen w-full overflow-hidden">
      <div
        className={`absolute inset-0 transition-opacity duration-700 ${
          concluido ? "opacity-0" : "opacity-100"
        }`}
      >
        <Loader />
        <Canvas
          camera={{ position: CAMERA_POS.toArray(), fov: 32, near: 0.1, far: 200 }}
          gl={{ antialias: true, alpha: true }}
          dpr={[1, 2]}
        >
          <ScrollControls pages={2} damping={0.2}>
            <Suspense fallback={null}>
              <Cena onFimChange={setConcluido} />
            </Suspense>
          </ScrollControls>
        </Canvas>
      </div>

      <div
        className={`pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-center transition-opacity duration-700 ${
          concluido ? "opacity-100" : "opacity-0"
        }`}
      >
        {children}
      </div>
    </div>
  );
}
